<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Brick\Math\BigDecimal;
use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfiguration;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentRefundActionResult;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\Refund as StripeRefund;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Performs "refund" payment method action.
 *
 * @see https://docs.stripe.com/api/refunds
 */
class RefundStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === PaymentMethodInterface::REFUND;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$this->isSupportedByActionName($stripeAction->getActionName())) {
            return false;
        }

        $refundTransaction = $stripeAction->getPaymentTransaction();
        $sourceTransaction = $refundTransaction->getSourcePaymentTransaction();
        if (!$sourceTransaction) {
            $this->logNoSourcePaymentTransaction($refundTransaction);

            return false;
        }

        $supportedSourceTransactionActions = [
            PaymentMethodInterface::PURCHASE,
            PaymentMethodInterface::CHARGE,
            PaymentMethodInterface::CAPTURE,
        ];
        if (!in_array($sourceTransaction->getAction(), $supportedSourceTransactionActions, true)) {
            return false;
        }

        if (!$sourceTransaction->getTransactionOption(StripePaymentIntentActionInterface::PAYMENT_INTENT_ID)) {
            $this->logNoStripePaymentIntentId($refundTransaction, $sourceTransaction);

            return false;
        }

        return true;
    }

    private function logNoSourcePaymentTransaction(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->notice(
            'Cannot refund the payment transaction #{paymentTransactionId}: no source payment transaction',
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
            'Cannot refund the payment transaction #{paymentTransactionId}: '
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
        $refundTransaction = $stripeAction->getPaymentTransaction();
        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $refundTransaction->getSourcePaymentTransaction();

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeClient->beginScopeFor($refundTransaction);

        $requestArgs = $this->prepareRequestArgs($stripeAction, $refundTransaction);
        $stripeRefund = $stripeClient->refunds->create(...$requestArgs);
        $this->processRequestResult($stripeRefund, $refundTransaction);

        // The source transaction has to be switched to inactive state after the refund.
        $sourceTransaction->setActive(!$refundTransaction->isSuccessful());

        return new StripePaymentIntentRefundActionResult(
            successful: $refundTransaction->isSuccessful(),
            stripeRefund: $stripeRefund
        );
    }

    private function prepareRequestArgs(
        StripePaymentIntentActionInterface $stripeAction,
        PaymentTransaction $refundTransaction
    ): array {
        /** @var PaymentTransaction $sourceTransaction */
        $sourceTransaction = $refundTransaction->getSourcePaymentTransaction();

        $refundReason = $refundTransaction->getTransactionOption(
            StripePaymentIntentActionInterface::REFUND_REASON
        ) ?? 'requested_by_customer';
        $stripeAmount = $this->stripeAmountConverter
            ->convertToStripeFormat((float)$refundTransaction->getAmount(), $refundTransaction->getCurrency());

        $requestArgs = [
            [
                'payment_intent' => $sourceTransaction->getTransactionOption(
                    StripePaymentIntentActionInterface::PAYMENT_INTENT_ID
                ),
                'reason' => $refundReason,
                'amount' => $stripeAmount,
                'metadata' => [
                    'payment_transaction_access_identifier' => $refundTransaction->getAccessIdentifier(),
                    'payment_transaction_access_token' => $refundTransaction->getAccessToken(),
                ],
            ],
        ];

        $beforeRequestEvent = new StripePaymentIntentActionBeforeRequestEvent(
            $stripeAction,
            'refundsCreate',
            $requestArgs
        );
        $this->eventDispatcher->dispatch($beforeRequestEvent);

        return $beforeRequestEvent->getRequestArgs();
    }

    private function processRequestResult(StripeRefund $stripeRefund, PaymentTransaction $refundTransaction): void
    {
        // More about Refund statuses:
        // https://docs.stripe.com/api/refunds/object#refund_object-status
        $successful = in_array($stripeRefund->status, ['succeeded', 'pending', 'requires_action']);

        $refundCurrency = mb_strtoupper(
            $stripeRefund->currency ?? CurrencyConfiguration::DEFAULT_CURRENCY
        );
        $refundAmount = $this->stripeAmountConverter->convertFromStripeFormat(
            $stripeRefund->amount ?? 0,
            $refundCurrency
        );

        $refundTransaction->setAction(PaymentMethodInterface::REFUND);
        $refundTransaction->setSuccessful(
            $successful &&
            BigDecimal::of($refundTransaction->getAmount())->isEqualTo(BigDecimal::of($refundAmount)) &&
            $refundTransaction->getCurrency() === $refundCurrency
        );
        $refundTransaction->setActive(false);
        $refundTransaction->setReference($stripeRefund->id);

        $refundTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $stripeRefund->payment_intent
        );
        $refundTransaction->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id);
    }
}
