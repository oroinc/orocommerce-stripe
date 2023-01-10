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

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getData(): ResponseObjectInterface
    {
        return $this->data;
    }

    public function getPaymentMethodIdentifier(): string
    {
        return $this->paymentConfig->getPaymentMethodIdentifier();
    }

    public function getPaymentConfig(): StripePaymentConfig
    {
        return $this->paymentConfig;
    }
}
