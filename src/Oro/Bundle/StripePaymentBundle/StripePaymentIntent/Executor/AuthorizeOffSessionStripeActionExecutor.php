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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "authorize" payment method action with the "off_session" option.
 * The difference from the regular "authorize" action is that this action is intended to be used
 * for charging via a previously authorized payment method - without user interaction.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class AuthorizeOffSessionStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly StripeCustomerActionExecutorInterface $stripeCustomerActionExecutor,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
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

        $authorizeTransaction = $stripeAction->getPaymentTransaction();
        if (!$authorizeTransaction->getTransactionOption(StripePaymentIntentActionInterface::OFF_SESSION)) {
            return false;
        }

        $paymentMethodId = $authorizeTransaction
            ->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID);
        if (!$paymentMethodId) {
            $this->logger->info(
                'Skipping "authorize" action: no payment method id found in the options '
                . 'of the payment transaction #{paymentTransactionId}',
                [
                    'paymentTransactionId' => $authorizeTransaction->getId(),
                ]
            );

            return false;
        }

        $stripePaymentIntentConfig = $stripeAction->getPaymentIntentConfig();

        return $stripeAction->getActionName() === PaymentMethodInterface::AUTHORIZE ||
            $stripePaymentIntentConfig->getCaptureMethod() === 'manual';
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
        $paymentIntentConfig = $stripeAction->getPaymentIntentConfig();
        $this->processRequestResult(
            $paymentIntent,
            $authorizeTransaction,
            $paymentIntentConfig->isReAuthorizationEnabled()
        );

        return new StripePaymentIntentActionResult(
            successful: $authorizeTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $authorizeTransaction,
    ): array {
        $stripeAmount = $this->stripeAmountConverter->convertToStripeFormat(
            (float)$authorizeTransaction->getAmount(),
            $authorizeTransaction->getCurrency()
        );
        $stripePaymentMethodId = $authorizeTransaction
            ->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID);

        $requestArgs = [
            [
                'amount' => $stripeAmount,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $stripePaymentMethodId,
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $stripeClientConfig = $stripeAction->getStripeClientConfig();
        $stripeCustomerId = $this->findOrCreateStripeCustomerId($stripeClientConfig, $authorizeTransaction);
        if ($stripeCustomerId) {
            $requestArgs[0]['customer'] = $stripeCustomerId;
        }

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function findOrCreateStripeCustomerId(
        StripeClientConfigInterface $stripeClientConfig,
        PaymentTransaction $authorizeTransaction
    ): ?string {
        $stripeCustomerId = $authorizeTransaction
            ->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID);

        if (!$stripeCustomerId) {
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
        }

        return $stripeCustomerId;
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $authorizeTransaction,
        bool $isReAuthorizationEnabled
    ): void {
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
            $isReAuthorizationEnabled
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
    }
}
