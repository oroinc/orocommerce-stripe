<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Stripe event object stores incoming data according to Stripe webhooks settings.
 */
class StripeEvent implements StripeEventInterface
{
    private string $eventName;
    private ResponseObjectInterface $data;
    private string $paymentMethodIdentifier;

    public function __construct(string $eventName, string $paymentMethodIdentifier, ResponseObjectInterface $data)
    {
        $this->eventName = $eventName;
        $this->paymentMethodIdentifier = $paymentMethodIdentifier;
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getEventName(): string
    {
        return $this->eventName;
    }

    /**
     * {@inheritdoc}
     */
    public function getData(): ResponseObjectInterface
    {
        return $this->data;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethodIdentifier(): string
    {
        return $this->paymentMethodIdentifier;
    }
}
