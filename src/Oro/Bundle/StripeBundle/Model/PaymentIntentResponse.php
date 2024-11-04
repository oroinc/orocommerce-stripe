<?php

namespace Oro\Bundle\StripeBundle\Model;

/**
 * Stores data for payment intent object responses.
 */
class PaymentIntentResponse extends AbstractResponseObject implements
    ResponseObjectInterface,
    PaymentIntentAwareInterface
{
    public const PAYMENT_INTENT_ID_PARAM = 'paymentIntentId';
    private const STATUS_FIELD_NAME = 'status';
    private const ID_FIELD_NAME = 'id';
    private const CLIENT_SECRET_FIELD_NAME = 'client_secret';
    private const NEXT_ACTION_FIELD_NAME = 'next_action';

    #[\Override]
    public function getStatus(): string
    {
        return $this->getValue(self::STATUS_FIELD_NAME);
    }

    #[\Override]
    public function getIdentifier(): string
    {
        return $this->getValue(self::ID_FIELD_NAME);
    }

    #[\Override]
    public function getData(): array
    {
        return [
            self::PAYMENT_INTENT_ID_PARAM => $this->getValue(self::ID_FIELD_NAME),
            'data' => [
                'amount' => $this->getValue('amount'),
                'amount_capturable' => $this->getValue('amount_capturable'),
                'amount_received' => $this->getValue('amount_received'),
                'canceled_at' => $this->getValue('canceled_at'),
                'cancellation_reason' => $this->getValue('cancellation_reason'),
                'capture_method' => $this->getValue('capture_method'),
                'confirmation_method' => $this->getValue('confirmation_method'),
                'created' => $this->getValue('created'),
                'currency' => $this->getValue('currency'),
                'customer' => $this->getValue('customer'),
                'invoice' => $this->getValue('invoice'),
                'latest_charge' => $this->getValue('latest_charge'),
                'last_payment_error' => $this->getValue('last_payment_error'),
                'livemode' => $this->getValue('livemode'),
                'metadata' => $this->getValue('metadata'),
                'next_action' => $this->getValue('next_action'),
                'payment_method' => $this->getValue('payment_method'),
                'processing' => $this->getValue('processing'),
                'status' => $this->getStatus(),
            ]
        ];
    }

    public function getNextActionType(): ?string
    {
        $nextAction = $this->getValue(self::NEXT_ACTION_FIELD_NAME);
        if (null !== $nextAction && isset($nextAction['type'])) {
            return $nextAction['type'];
        }

        return null;
    }

    public function getClientSecret(): ?string
    {
        return $this->getValue(self::CLIENT_SECRET_FIELD_NAME);
    }

    #[\Override]
    public function getPaymentIntentId(): ?string
    {
        return $this->getIdentifier();
    }
}
