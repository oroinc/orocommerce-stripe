<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Stores data for charge object responses.
 */
class ChargeResponse extends AbstractResponseObject implements ResponseObjectInterface, PaymentIntentAwareInterface
{
    private const PAYMENT_INTENT_ID_FIELD = 'payment_intent';
    private const STATUS_FIELD = 'status';
    private const ID_FIELD = 'id';

    public function getPaymentIntentId(): ?string
    {
        return $this->getValue(self::PAYMENT_INTENT_ID_FIELD);
    }

    public function getStatus(): string
    {
        return $this->getValue(self::STATUS_FIELD);
    }

    public function getIdentifier(): string
    {
        return $this->getValue(self::ID_FIELD);
    }

    public function getData(): array
    {
        return [
            'data' => [
                'id' => $this->getIdentifier(),
                'amount' => $this->getValue('amount'),
                'amount_captured' => $this->getValue('amount_captured'),
                'amount_refunded' => $this->getValue('amount_refunded'),
                'balance_transaction' => $this->getValue('balance_transaction'),
                'billing_details' => $this->getValue('billing_details'),
                'captured' => $this->getValue('captured'),
                'currency' => $this->getValue('currency'),
                'created' => $this->getValue('created'),
                'failure_code' => $this->getValue('failure_code'),
                'failure_message' => $this->getValue('failure_message'),
                'fraud_details' => $this->getValue('fraud_details'),
                'payment_intent' => $this->getValue('payment_intent'),
                'payment_method' => $this->getValue('payment_method'),
                'refunds' => $this->getValue('refunds'),
                'status' => $this->getValue('status')
            ]
        ];
    }
}
