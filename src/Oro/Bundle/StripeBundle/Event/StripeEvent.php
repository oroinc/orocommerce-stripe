<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Stripe event object stores incoming data according to Stripe webhooks settings.
 */
class StripeEvent implements StripeEventInterface
{
    public function __construct(
        private string $eventName,
        private StripePaymentConfig $paymentConfig,
        private ResponseObjectInterface $data,
        private ?string $paymentMethodIdentifier = null
    ) {
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
        return $this->paymentMethodIdentifier ?? $this->paymentConfig->getPaymentMethodIdentifier();
    }

    #[\Override]
    public function getPaymentConfig(): StripePaymentConfig
    {
        return $this->paymentConfig;
    }
}
