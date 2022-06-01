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

        $cancelTransactions = $this->getPaymentTransactionRepository()->findSuccessfulRelatedTransactionsByAction(
            $sourceTransaction,
            PaymentMethodInterface::CANCEL
        );

        // Check if transaction has not been already created by API Call.
        if (empty($cancelTransactions) || !$this->alreadyExists($cancelTransactions, $responseObject)) {
            //Create transaction with PaymentMethodInterface::CANCEL type. As partial refund is allowed there could be
            // more than one canceled transactions. We don't compare refunded amounts with captured amount, this is
            // Stripe service responsibility.
            $cancelPaymentTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
                PaymentMethodInterface::CANCEL,
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

    private function alreadyExists(array $transactions, ChargeResponse $responseObject): bool
    {
        // To identify existing transaction just compare total refunds.
        $existingTransaction = array_filter(
            $transactions,
            function (PaymentTransaction $transaction) use ($responseObject) {
                $transactionResponseData = $transaction->getResponse();
                $transactionCharge = new ChargeResponse($transactionResponseData['data']);
                return $transactionCharge->getRefundsCount() === $responseObject->getRefundsCount();
            }
        );

        return !empty($existingTransaction);
    }

    private function getRefundedAmount(ChargeResponse $response): float
    {
        return PaymentAmountConverter::convertFromStripeFormat($response->getLastRefundedAmount());
    }
}
