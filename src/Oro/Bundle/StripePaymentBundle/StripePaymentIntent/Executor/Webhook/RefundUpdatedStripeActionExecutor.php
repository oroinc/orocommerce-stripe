<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\Webhook;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentRefundActionResult;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\Refund as StripeRefund;

/**
 * Handles the "refund.updated" webhook event as a payment method action.
 *
 * @see https://docs.stripe.com/api/refunds
 * @see https://docs.stripe.com/api/events/object
 * @see https://docs.stripe.com/api/events/types#event_types-refund.updated
 */
class RefundUpdatedStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const string EVENT_TYPE = 'refund.updated';
    public const string ACTION_NAME = 'webhook:' . self::EVENT_TYPE;

    public function __construct(
        private readonly PaymentTransactionProvider $paymentTransactionProvider,
        private readonly StripeAmountConverterInterface $stripeAmountConverter
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === self::ACTION_NAME;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$stripeAction instanceof StripePaymentIntentWebhookActionInterface) {
            return false;
        }

        if ($stripeAction->getStripeEvent()->type !== self::EVENT_TYPE) {
            return false;
        }

        $purchaseTransaction = $stripeAction->getPaymentTransaction();
        $supportedTransactionActions = [
            PaymentMethodInterface::PURCHASE,
            PaymentMethodInterface::CHARGE,
            PaymentMethodInterface::CAPTURE,
        ];
        if (!in_array($purchaseTransaction->getAction(), $supportedTransactionActions, true)) {
            return false;
        }

        return true;
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        if (!$stripeAction instanceof StripePaymentIntentWebhookActionInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument $stripeAction is expected to be an instance of %s, got %s',
                    StripePaymentIntentWebhookActionInterface::class,
                    get_debug_type($stripeAction)
                )
            );
        }

        $purchaseTransaction = $stripeAction->getPaymentTransaction();

        $stripeEvent = $stripeAction->getStripeEvent();
        /** @var StripeRefund $stripeRefund */
        $stripeRefund = $stripeEvent->data->object ?? null;

        // More about Refund statuses:
        // @see https://docs.stripe.com/api/refunds/object#refund_object-status
        $isSuccessful = in_array($stripeRefund->status, ['succeeded', 'pending', 'requires_action']);
        $isActive = in_array($stripeRefund->status, ['pending', 'requires_action']);

        $refundCurrency = mb_strtoupper($stripeRefund->currency ?? 'USD');
        $refundAmount = $this->stripeAmountConverter->convertFromStripeFormat(
            $stripeRefund->amount ?? 0,
            $refundCurrency
        );

        $refundTransaction = $this->findOrCreateRefundTransaction($purchaseTransaction, $stripeRefund);

        $refundTransaction->setAmount($refundAmount);
        $refundTransaction->setCurrency($refundCurrency);
        $refundTransaction->setSuccessful($isSuccessful);
        $refundTransaction->setActive($isActive);
        $refundTransaction->setReference($stripeRefund->id);
        $refundTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::REFUND_REASON,
            $stripeRefund->reason
        );
        $refundTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $stripeRefund->payment_intent ?? ''
        );
        $refundTransaction->addTransactionOption(StripePaymentIntentActionInterface::REFUND_ID, $stripeRefund->id);
        $refundTransaction->addWebhookRequestLog($stripeEvent->toArray());

        $this->paymentTransactionProvider->savePaymentTransaction($refundTransaction);

        $purchaseTransaction->setActive($refundTransaction->isActive());

        return new StripePaymentIntentRefundActionResult(successful: true, stripeRefund: $stripeRefund);
    }

    private function findOrCreateRefundTransaction(
        PaymentTransaction $purchaseTransaction,
        StripeRefund $stripeRefund
    ): PaymentTransaction {
        $refundTransaction = $this->paymentTransactionProvider->findOrCreateByPaymentTransaction(
            PaymentMethodInterface::REFUND,
            $purchaseTransaction,
            ['reference' => $stripeRefund->id]
        );

        if ($refundTransaction->getId() && !$refundTransaction->isActive()) {
            $this->logger->warning(
                'Unexpected state while handling the event "{eventType}" '
                . 'on the payment transaction #{purchasePaymentTransactionId}: '
                . 'the existing "refund" transaction #{refundPaymentTransactionId} is not active',
                [
                    'eventType' => self::EVENT_TYPE,
                    'purchasePaymentTransactionId' => $purchaseTransaction->getId(),
                    'refundPaymentTransactionId' => $refundTransaction->getId(),
                ]
            );
        }

        return $refundTransaction;
    }
}
