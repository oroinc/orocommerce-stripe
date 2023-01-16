<?php

namespace Oro\Bundle\StripeBundle\Model;

use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;

/**
 * Stores data for refund object responses.
 */
class RefundResponse extends AbstractResponseObject implements RefundResponseInterface, PaymentIntentAwareInterface
{
    private const IDENTIFIER_FIELD = 'id';
    private const PAYMENT_INTENT_FIELD = 'payment_intent';
    private const STATUS_FIELD = 'status';

    public function getPaymentIntentId(): ?string
    {
        return $this->getValue(self::PAYMENT_INTENT_FIELD);
    }

    public function getStatus(): string
    {
        return $this->getValue(self::STATUS_FIELD);
    }

    public function getIdentifier(): string
    {
        return $this->getValue(self::IDENTIFIER_FIELD);
    }

    public function getData(): array
    {
        return [
            'data' => [
                'id' => $this->getIdentifier(),
                'amount' => $this->getValue('amount'),
                'balance_transaction' => $this->getValue('balance_transaction'),
                'currency' => $this->getValue('currency'),
                'payment_intent' => $this->getPaymentIntentId(),
                'metadata' => $this->getValue('metadata'),
                'reason' => $this->getValue('reason'),
                'status' => $this->getStatus()
            ]
        ];
    }

    public function getRefundedAmount(): float
    {
        $amount = $this->getValue('amount');

        if (!$amount) {
            throw new \LogicException('Refund amount could not be empty');
        }
        return PaymentAmountConverter::convertFromStripeFormat($amount);
    }
}
