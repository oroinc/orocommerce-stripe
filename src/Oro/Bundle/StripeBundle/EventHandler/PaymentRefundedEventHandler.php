<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentAwareInterface;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Handle refund event data.
 */
class PaymentRefundedEventHandler extends AbstractStripeEventHandler implements StripeEventHandlerInterface
{
    private const PAYMENT_REFUNDED_EVENT = 'charge.refunded';

    /**
     * {@inheritdoc}
     */
    protected function createPaymentTransaction(
        ResponseObjectInterface $responseObject,
        string $paymentMethodIdentifier
    ): void {
        if (!$responseObject instanceof PaymentIntentAwareInterface) {
            throw new \LogicException(
                sprintf('Unexpected response type object. It should be of %s type', PaymentIntentAwareInterface::class)
            );
        }

        // Only captured payments could be refunded.
        $sourceTransaction = $this->findSourceTransaction(
            $responseObject->getPaymentIntentId(),
            PaymentMethodInterface::CAPTURE,
            $paymentMethodIdentifier
        );

        if (!$sourceTransaction) {
            throw new StripeEventHandleException('`Payment could not be refunded. There are no capture transaction`');
        }

        $transactionShouldBeCreated = $this->transactionShouldBeCreated($sourceTransaction);

        // Check if transaction has not been already created by API Call.
        if ($transactionShouldBeCreated) {
            //Create transaction with PaymentMethodInterface::CANCEL type. As partial refund is allowed there could be
            // more than one canceled transactions. We don't compare refunded amounts with captured amount, this is
            // Stripe service responsibility.
            $cancelPaymentTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
                PaymentMethodInterface::REFUND,
                $sourceTransaction
            );

            $cancelPaymentTransaction->setActive(false);
            $cancelPaymentTransaction->setAmount($this->getRefundedAmount($responseObject));

            $this->updateAndSaveTransaction(
                $responseObject,
                $cancelPaymentTransaction,
                $sourceTransaction
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isSupported(StripeEventInterface $event): bool
    {
        return $event->getEventName() === self::PAYMENT_REFUNDED_EVENT;
    }

    private function getRefundedAmount(ChargeResponse $response): float
    {
        return PaymentAmountConverter::convertFromStripeFormat($response->getLastRefundedAmount());
    }

    private function transactionShouldBeCreated(PaymentTransaction $sourceTransaction): bool
    {
        $transactions = $this->getPaymentTransactionRepository()->findBy([
            'sourcePaymentTransaction' => $sourceTransaction,
            'action' => PaymentMethodInterface::REFUND
        ]);

        $refundedAmount = 0.00;
        foreach ($transactions as $transaction) {
            // Amount already captured successfully or cancellation is in progress.
            if ($this->isInProgress($transaction)) {
                return false;
            }

            if ($transaction->isSuccessful()) {
                $refundedAmount += (float)$transaction->getAmount();
            }
        }

        return (float) $sourceTransaction->getAmount() > $refundedAmount;
    }

    private function isInProgress(PaymentTransaction $transaction): bool
    {
        return $transaction->isActive() && !$transaction->isSuccessful() && !$transaction->getReference();
    }
}
