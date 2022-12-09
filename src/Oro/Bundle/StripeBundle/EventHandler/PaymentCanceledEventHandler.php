<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Handle authorized payment transaction cancel. It could be initiated only in full amount.
 */
class PaymentCanceledEventHandler extends AbstractStripeEventHandler implements StripeEventHandlerInterface
{
    private const PAYMENT_INTENT_CANCELED_EVENT = 'payment_intent.canceled';

    /**
     * {@inheritdoc}
     */
    public function isSupported(StripeEventInterface $event): bool
    {
        return $event->getEventName() === self::PAYMENT_INTENT_CANCELED_EVENT;
    }

    protected function createPaymentTransaction(
        ResponseObjectInterface $responseObject,
        string $paymentMethodIdentifier
    ): void {
        // Only Authorized transactions could be captured.
        $sourceTransaction = $this->findSourceTransaction(
            $responseObject->getIdentifier(),
            PaymentMethodInterface::AUTHORIZE,
            $paymentMethodIdentifier
        );

        if (!$sourceTransaction) {
            throw new StripeEventHandleException(
                'Unable to cancel transaction: correspond authorized transaction could not be found'
            );
        }

        $transactionShouldBeCreated = $this->transactionShouldBeCreated($sourceTransaction);

        if ($transactionShouldBeCreated) {
            $cancelPaymentTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
                PaymentMethodInterface::CANCEL,
                $sourceTransaction
            );

            $cancelPaymentTransaction->setActive(false);
            $sourceTransaction->setActive(false);

            $this->updateAndSaveTransaction(
                $responseObject,
                $cancelPaymentTransaction,
                $sourceTransaction
            );
        }
    }

    private function transactionShouldBeCreated(PaymentTransaction $sourceTransaction): bool
    {
        $transactions = $this->getPaymentTransactionRepository()->findBy([
            'sourcePaymentTransaction' => $sourceTransaction,
            'action' => PaymentMethodInterface::CANCEL
        ]);

        foreach ($transactions as $transaction) {
            // Amount already captured successfully or cancellation is in progress.
            if ($transaction->isSuccessful() || $this->isInProgress($transaction)) {
                return false;
            }
        }

        return true;
    }

    private function isInProgress(PaymentTransaction $transaction): bool
    {
        return $transaction->isActive() && !$transaction->isSuccessful() && !$transaction->getReference();
    }
}
