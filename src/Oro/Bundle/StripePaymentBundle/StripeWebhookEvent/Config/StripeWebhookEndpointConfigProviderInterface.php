<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Config;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointConfigInterface;

/**
 * Provides the Stripe WebhookEndpoints configuration for the given webhook access ID.
 */
interface StripeWebhookEndpointConfigProviderInterface
{
    public function getStripeWebhookEndpointConfig(string $webhookAccessId): ?StripeWebhookEndpointConfigInterface;
}
