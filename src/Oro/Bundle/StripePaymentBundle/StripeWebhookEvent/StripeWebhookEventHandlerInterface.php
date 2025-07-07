<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent;

/**
 * Handles the Stripe Event webhook.
 */
interface StripeWebhookEventHandlerInterface
{
    public function onWebhookEvent(StripeWebhookEvent $event): void;
}
