<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Handle payment success event triggered when payment captured. Implements logic to handle manual capture (from Stripe
 * Dashboard) only.
 */
class PaymentSuccessEventHandler extends AbstractStripeEventHandler implements StripeEventHandlerInterface
{
    private const PAYMENT_INTENT_SUCCEEDED_EVENT = 'payment_intent.succeeded';

    public function isSupported(StripeEventInterface $event): bool
    {
        return $event->getEventName() === self::PAYMENT_INTENT_SUCCEEDED_EVENT;
    }

    protected function createPaymentTransaction(
        ResponseObjectInterface $responseObject,
        string $paymentMethodIdentifier
    ):void {
        $authorizationTransaction = $this->findSourceTransaction(
            $responseObject->getIdentifier(),
            PaymentMethodInterface::AUTHORIZE,
            $paymentMethodIdentifier
        );

        // Skip further actions if there are no previously authorized transaction. We handle only manual capturing which
        // is available only for authorized payments.
        if (!$authorizationTransaction) {
            return;
        }

        // Check if capture transaction does not exist. There are no ability to check if this event has been triggered
        // by API call from application or by manual capturing from Stripe Dashboard.
        if (!$this->captureTransactionAlreadyExists($authorizationTransaction)) {
            $capturePaymentTransaction = $this->paymentTransactionProvider->createPaymentTransactionByParentTransaction(
                PaymentMethodInterface::CAPTURE,
                $authorizationTransaction
            );

            $capturePaymentTransaction->setAmount($this->getReceivedAmount($responseObject));
            $capturePaymentTransaction->setActive(false);
            $authorizationTransaction->setActive(false);

            $this->updateAndSaveTransaction(
                $responseObject,
                $capturePaymentTransaction,
                $authorizationTransaction
            );
        }
    }

    private function getReceivedAmount(ResponseObjectInterface $responseObject): ?float
    {
        $responseData = $responseObject->getData();
        if (isset($responseData['data']['amount_received'])) {
            return PaymentAmountConverter::convertFromStripeFormatUsingCurrency(
                $responseData['data']['amount_received'],
                $responseData['data']['currency'],
            );
        }

        return null;
    }

    private function captureTransactionAlreadyExists(PaymentTransaction $sourceTransaction): bool
    {
        $transactions = $this->getPaymentTransactionRepository()->findBy([
            'sourcePaymentTransaction' => $sourceTransaction,
            'action' => PaymentMethodInterface::CAPTURE
        ]);

        foreach ($transactions as $transaction) {
            // Amount already captured successfully
            if ($transaction->isSuccessful()) {
                return true;
            }

            // Capture request sent from API but response still not proceeded or transaction not updated at this moment.
            if ($transaction->isActive() && !$transaction->getReference()) {
                return true;
            }
        }

        return false;
    }
}
