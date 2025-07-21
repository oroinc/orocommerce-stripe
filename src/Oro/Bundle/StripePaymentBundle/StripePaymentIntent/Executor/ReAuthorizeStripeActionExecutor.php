<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "re_authorize" payment method action.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class ReAuthorizeStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly PaymentTransactionProvider $paymentTransactionProvider,
        private readonly StripePaymentIntentActionExecutorInterface $cancelPaymentIntentsMethodAction,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === PaymentMethodInterface::RE_AUTHORIZE;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $reAuthorizeTransaction = $stripeAction->getPaymentTransaction();
        $sourceTransaction = $reAuthorizeTransaction->getSourcePaymentTransaction();
        if (!$sourceTransaction) {
            $this->logNoSourcePaymentTransaction($reAuthorizeTransaction);

            return false;
        }

        if ($sourceTransaction->getAction() !== PaymentMethodInterface::AUTHORIZE ||
            !$sourceTransaction->getTransactionOption(
                ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED
            )) {
            return false;
        }

        if (!$sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::CUSTOMER_ID)) {
            $this->logNoStripeCustomerId($reAuthorizeTransaction, $sourceTransaction);

            return false;
        }

        if (!$sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_METHOD_ID)) {
            $this->logNoStripePaymentMethodId($reAuthorizeTransaction, $sourceTransaction);

            return false;
        }

        return true;
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $reAuthorizeTransaction = $stripeAction->getPaymentTransaction();
        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $reAuthorizeTransaction->getSourcePaymentTransaction();
        $cancelTransaction = $this->createPaymentTransaction(PaymentMethodInterface::CANCEL, $sourceTransaction);
        $authorizeTransaction = $this->createPaymentTransaction(PaymentMethodInterface::AUTHORIZE, $sourceTransaction);

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($authorizeTransaction);

        $requestArgs = $this->prepareRequestArgs($stripeAction, $authorizeTransaction);
        $paymentIntent = $stripeClient->paymentIntents->create(...$requestArgs);
        $this->processRequestResult($paymentIntent, $authorizeTransaction);

        $reAuthorizeTransaction->setSuccessful($authorizeTransaction->isSuccessful());

        if ($authorizeTransaction->isSuccessful()) {
            $this->cancelPreviousAuthorization(
                $cancelTransaction,
                $reAuthorizeTransaction,
                $stripeAction
            );
        }

        return new StripePaymentIntentActionResult(
            successful: $reAuthorizeTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $authorizeTransaction
    ): array {
        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $authorizeTransaction->getSourcePaymentTransaction();

        $stripeAmount = $this->stripeAmountConverter->convertToStripeFormat(
            (float)$authorizeTransaction->getAmount(),
            $authorizeTransaction->getCurrency()
        );

        $requestArgs = [
            [
                'amount' => $stripeAmount,
                'currency' => $authorizeTransaction->getCurrency(),
                'capture_method' => 'manual',
                'confirm' => true,
                'off_session' => true,
                'payment_method' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
                ),
                'customer' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::CUSTOMER_ID
                ),
                'metadata' => [
                    'payment_transaction_access_identifier' => $authorizeTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $authorizeTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCreate',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $authorizeTransaction
    ): void {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        $successful = $paymentIntent->status === 'requires_capture';

        $authorizeTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $authorizeTransaction->setSuccessful($successful);
        // Active as the transaction is waiting to be manually captured.
        $authorizeTransaction->setActive($successful);
        $authorizeTransaction->setReference($paymentIntent->id);

        $authorizeTransaction->addTransactionOption(ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED, true);
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

        $this->paymentTransactionProvider->savePaymentTransaction($authorizeTransaction);

        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $authorizeTransaction->getSourcePaymentTransaction();
        // The source transaction has to be switched to inactive state after the re-authorization.
        $sourceTransaction->setActive(!$authorizeTransaction->isSuccessful());
    }

    private function logNoSourcePaymentTransaction(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
            . 'no source payment transaction',
            [
                'reAuthorizePaymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function logNoStripeCustomerId(
        PaymentTransaction $paymentTransaction,
        PaymentTransaction $sourcePaymentTransaction
    ): void {
        $this->logger->notice(
            'Cannot re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
            . 'stripeCustomerId is not found in #{sourcePaymentTransactionId}',
            [
                'reAuthorizePaymentTransactionId' => $paymentTransaction->getId(),
                'sourcePaymentTransactionId' => $sourcePaymentTransaction->getId(),
            ]
        );
    }

    private function logNoStripePaymentMethodId(
        PaymentTransaction $reAuthorizePaymentTransaction,
        PaymentTransaction $sourcePaymentTransaction
    ): void {
        $this->logger->notice(
            'Cannot re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
            . 'stripePaymentMethodId is not found in #{sourcePaymentTransactionId}',
            [
                'reAuthorizePaymentTransactionId' => $reAuthorizePaymentTransaction->getId(),
                'sourcePaymentTransactionId' => $sourcePaymentTransaction->getId(),
            ]
        );
    }

    private function createPaymentTransaction(
        string $action,
        PaymentTransaction $sourcePaymentTransaction
    ): PaymentTransaction {
        $cancelPaymentTransaction = $this->paymentTransactionProvider
            ->createPaymentTransactionByParentTransaction($action, $sourcePaymentTransaction);
        $this->paymentTransactionProvider->savePaymentTransaction($cancelPaymentTransaction);

        return $cancelPaymentTransaction;
    }

    private function cancelPreviousAuthorization(
        PaymentTransaction $cancelTransaction,
        PaymentTransaction $reAuthorizeTransaction,
        StripePaymentIntentActionInterface $stripePaymentAction
    ): void {
        try {
            $stripeActionResult = $this->cancelPaymentIntentsMethodAction->executeAction(
                new StripePaymentIntentAction(
                    actionName: PaymentMethodInterface::CANCEL,
                    stripePaymentIntentConfig: $stripePaymentAction->getStripeClientConfig(),
                    paymentTransaction: $cancelTransaction
                )
            );
        } finally {
            $this->paymentTransactionProvider->savePaymentTransaction($cancelTransaction);

            if (!isset($stripeActionResult) || !$stripeActionResult->isSuccessful()) {
                $sourceTransaction = $cancelTransaction->getSourcePaymentTransaction();

                $this->logger->error(
                    'Failed to re-authorize the payment transaction #{reAuthorizePaymentTransactionId}: '
                    . 'failed to cancel the source payment transaction #{sourcePaymentTransactionId}',
                    [
                        'reAuthorizePaymentTransactionId' => $reAuthorizeTransaction->getId(),
                        'sourcePaymentTransactionId' => $sourceTransaction?->getId(),
                    ]
                );
            }
        }
    }
}
