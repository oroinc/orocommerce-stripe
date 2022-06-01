<?php

namespace Oro\Bundle\StripeBundle\EventHandler;

use Oro\Bundle\StripeBundle\Event\StripeEventInterface;

/**
 * Provides basic methods to handle event data.
 */
interface StripeEventHandlerInterface
{
    /**
     * Handle events triggered by Stripe service.
     *
     * @param StripeEventInterface $event
     */
    public function handle(StripeEventInterface $event): void;

    /**
     * Check if handler is able to handle event.
     *
     * @param StripeEventInterface $event
     * @return bool
     */
    public function isSupported(StripeEventInterface $event): bool;
}
