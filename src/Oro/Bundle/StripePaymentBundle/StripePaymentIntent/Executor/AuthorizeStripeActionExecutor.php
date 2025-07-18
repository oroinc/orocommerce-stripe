<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "authorize" payment method action.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class AuthorizeStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly StripeCustomerActionExecutorInterface $stripeCustomerActionExecutor,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return in_array(
            $stripeActionName,
            [PaymentMethodInterface::PURCHASE, PaymentMethodInterface::AUTHORIZE],
            true
        );
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $stripePaymentIntentConfig = $stripeAction->getPaymentIntentConfig();
        if ($stripeAction->getActionName() === PaymentMethodInterface::PURCHASE &&
            $stripePaymentIntentConfig->getCaptureMethod() !== 'manual') {
            return false;
        }

        $authorizeTransaction = $stripeAction->getPaymentTransaction();
        $confirmationToken = $this->getAdditionalData($authorizeTransaction, 'confirmationToken');
        if (empty($confirmationToken['id']) || empty($confirmationToken['paymentMethodPreview']['type'])) {
            $this->logNoConfirmationToken($authorizeTransaction);

            return false;
        }

        return in_array(
            $confirmationToken['paymentMethodPreview']['type'],
            $stripePaymentIntentConfig->getPaymentMethodTypesWithManualCapture(),
            true
        );
    }

    private function logNoConfirmationToken(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot authorize the payment transaction #{paymentTransactionId}: confirmationToken data is missing',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function logNoStripeCustomerId(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot find or create a Stripe customer for the payment transaction #{paymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $authorizeTransaction = $stripeAction->getPaymentTransaction();

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($authorizeTransaction);

        $requestArgs = $this->prepareRequestArgs($stripeAction, $authorizeTransaction);
        $paymentIntent = $stripeClient->paymentIntents->create(...$requestArgs);
        $this->processRequestResult($paymentIntent, $authorizeTransaction);

        return new StripePaymentIntentActionResult(
            successful: $authorizeTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $authorizeTransaction,
    ): array {
        $stripeClientConfig = $stripeAction->getStripeClientConfig();
        $stripeAmount = $this->stripeAmountConverter->convertToStripeFormat(
            (float)$authorizeTransaction->getAmount(),
            $authorizeTransaction->getCurrency()
        );
        $confirmationToken = $this->getAdditionalData($authorizeTransaction, 'confirmationToken');
        $stripePaymentMethodType = $confirmationToken['paymentMethodPreview']['type'] ?? '';
        $stripeCustomerId = $this->findOrCreateStripeCustomerId($stripeClientConfig, $authorizeTransaction);

        $requestArgs = [
            [
                'amount' => $stripeAmount,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'automatic',
                'automatic_payment_methods' => ['enabled' => true],
                'payment_method_options' => [
                    $stripePaymentMethodType => ['capture_method' => 'manual'],
                ],
                'confirm' => true,
                // Required when confirm is true.
                'return_url' => $this->generateReturnUrl($authorizeTransaction),
                'confirmation_token' => $confirmationToken['id'] ?? '',
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        if ($stripeCustomerId) {
            $requestArgs[0]['customer'] = $stripeCustomerId;
        }

        $paymentIntentConfig = $stripeAction->getPaymentIntentConfig();
        if ($paymentIntentConfig->isReAuthorizationEnabled()) {
            $requestArgs[0]['setup_future_usage'] = 'off_session';
        }

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function getAdditionalData(PaymentTransaction $paymentTransaction, string $key): mixed
    {
        return $paymentTransaction->getTransactionOptions()['additionalData'][$key] ?? null;
    }

    private function generateReturnUrl(PaymentTransaction $paymentTransaction): ?string
    {
        return $this->urlGenerator?->generate(
            'oro_payment_callback_return',
            ['accessIdentifier' => $paymentTransaction->getAccessIdentifier()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function findOrCreateStripeCustomerId(
        StripeClientConfigInterface $stripeClientConfig,
        PaymentTransaction $authorizeTransaction
    ): ?string {
        $stripeActionResult = $this->stripeCustomerActionExecutor->executeAction(
            new FindOrCreateStripeCustomerAction(
                stripeClientConfig: $stripeClientConfig,
                paymentTransaction: $authorizeTransaction
            )
        );

        $stripeCustomerId = $stripeActionResult->getStripeObject()?->id;
        if ($stripeCustomerId === null) {
            $this->logNoStripeCustomerId($authorizeTransaction);
        }

        return $stripeCustomerId;
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $authorizeTransaction
    ): bool {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        $successful = $paymentIntent->status === 'requires_capture';
        $requiresAction = $paymentIntent->status === 'requires_action';

        $authorizeTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $authorizeTransaction->setSuccessful($successful);
        // Active if the transaction is waiting for action or to be manually captured.
        $authorizeTransaction->setActive($requiresAction || $successful);
        $authorizeTransaction->setReference($paymentIntent->id);

        $authorizeTransaction->addTransactionOption(
            ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED,
            isset($paymentIntent->setup_future_usage)
        );
        $authorizeTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $paymentIntent->id
        );
        $authorizeTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_METHOD_ID,
            $paymentIntent->payment_method ?? ''
        );
        $authorizeTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::CUSTOMER_ID,
            $paymentIntent->customer ?? ''
        );

        return $requiresAction;
    }
}
