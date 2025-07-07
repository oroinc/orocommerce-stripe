<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Factory;

use Oro\Bundle\PaymentBundle\Event\CallbackHandler;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Stripe\Webhook as StripeWebhook;

/**
 * Creates the {@see StripeWebhookEvent} by the given webhook access ID, webhook payload and webhook signature.
 *
 * The created event can be dispatched via {@see CallbackHandler}.
 */
interface StripeCallbackWebhookEventFactoryInterface
{
    public function createStripeCallbackWebhookEvent(
        StripeWebhookEndpointConfigInterface $stripeWebhookEndpointConfig,
        string $webhookPayload,
        string $webhookSignature,
        int $tolerance = StripeWebhook::DEFAULT_TOLERANCE
    ): ?StripeWebhookEvent;
}
