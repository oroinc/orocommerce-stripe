<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Provides basic methods to get values from event data.
 */
interface StripeEventInterface
{
    /**
     * Identify event name.
     *
     * @return string
     */
    public function getEventName(): string;

    /**
     * Get event values from Stripe event.
     *
     * @return ResponseObjectInterface
     */
    public function getData(): ResponseObjectInterface;

    /**
     * Get configured Stripe payment method identifier.
     *
     * @return string
     */
    public function getPaymentMethodIdentifier(): string;
}
