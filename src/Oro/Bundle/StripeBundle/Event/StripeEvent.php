<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Stripe event object stores incoming data according to Stripe webhooks settings.
 */
class StripeEvent implements StripeEventInterface
{
    private string $eventName;
    private ResponseObjectInterface $data;
    private StripePaymentConfig $paymentConfig;

    public function __construct(
        string $eventName,
        StripePaymentConfig $paymentConfig,
        ResponseObjectInterface $data
    ) {
        $this->eventName = $eventName;
        $this->paymentConfig = $paymentConfig;
        $this->data = $data;
    }

    #[\Override]
    public function getEventName(): string
    {
        return $this->eventName;
    }

    #[\Override]
    public function getData(): ResponseObjectInterface
    {
        return $this->data;
    }

    #[\Override]
    public function getPaymentMethodIdentifier(): string
    {
        return $this->paymentConfig->getPaymentMethodIdentifier();
    }

    #[\Override]
    public function getPaymentConfig(): StripePaymentConfig
    {
        return $this->paymentConfig;
    }
}
