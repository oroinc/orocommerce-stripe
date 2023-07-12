<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Provides basic methods to get values from event data.
 */
interface StripeEventInterface
{
    /**
     * Identify event name.
     */
    public function getEventName(): string;

    /**
     * Get event values from Stripe event.
     */
    public function getData(): ResponseObjectInterface;

    /**
     * Get configured Stripe payment method identifier.
     */
    public function getPaymentMethodIdentifier(): string;

    /**
     * Get payment configuration settings bag.
     */
    public function getPaymentConfig(): StripePaymentConfig;
}
