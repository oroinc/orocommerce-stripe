<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent as StripePaymentIntent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "cancel" payment method action.
 *
 * @see https://docs.stripe.com/api/payment_intents
 */
class CancelStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === PaymentMethodInterface::CANCEL;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $cancelTransaction = $stripeAction->getPaymentTransaction();
        $sourceTransaction = $cancelTransaction->getSourcePaymentTransaction();
        if (!$sourceTransaction) {
            $this->logNoSourcePaymentTransaction($cancelTransaction);

            return false;
        }

        if ($sourceTransaction->getAction() !== PaymentMethodInterface::AUTHORIZE) {
            return false;
        }

        if (!$sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)) {
            $this->logNoStripePaymentIntentId($cancelTransaction, $sourceTransaction);

            return false;
        }

        return true;
    }

    private function logNoSourcePaymentTransaction(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot cancel the payment transaction #{paymentTransactionId}: no source payment transaction',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
            ]
        );
    }

    private function logNoStripePaymentIntentId(
        PaymentTransaction $paymentTransaction,
        PaymentTransaction $sourcePaymentTransaction
    ): void {
        $this->logger->notice(
            'Cannot cancel the payment transaction #{paymentTransactionId}: '
            . 'stripePaymentIntentId is not found in #{sourcePaymentTransactionId}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
                'sourcePaymentTransactionId' => $sourcePaymentTransaction->getId(),
            ]
        );
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        $cancelTransaction = $stripeAction->getPaymentTransaction();

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($cancelTransaction);

        $requestArgs = $this->prepareRequestArgs($stripeAction, $cancelTransaction);
        $paymentIntent = $stripeClient->paymentIntents->cancel(...$requestArgs);
        $this->processRequestResult($paymentIntent, $cancelTransaction);

        return new StripePaymentIntentActionResult(
            successful: $cancelTransaction->isSuccessful(),
            stripePaymentIntent: $paymentIntent
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $cancelTransaction
    ): array {
        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $cancelTransaction->getSourcePaymentTransaction();

        $cancelReason = $cancelTransaction->getTransactionOption(
            StripePaymentIntentActionInterface::CANCEL_REASON
        ) ?? 'requested_by_customer';
        $requestArgs = [
            $sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID),
            [
                'cancellation_reason' => $cancelReason,
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'paymentIntentsCancel',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function processRequestResult(
        StripePaymentIntent $paymentIntent,
        PaymentTransaction $cancelTransaction
    ): void {
        // More about Payment Intent statuses:
        // https://docs.stripe.com/payments/paymentintents/lifecycle#intent-statuses
        $successful = $paymentIntent->status === 'canceled';

        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setSuccessful($successful);
        $cancelTransaction->setActive(false);
        $cancelTransaction->setReference($paymentIntent->id);

        $cancelTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $paymentIntent->id
        );
    }
}
