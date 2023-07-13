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
     */
    public function handle(StripeEventInterface $event): void;

    /**
     * Check if handler is able to handle event.
     */
    public function isSupported(StripeEventInterface $event): bool;
}
