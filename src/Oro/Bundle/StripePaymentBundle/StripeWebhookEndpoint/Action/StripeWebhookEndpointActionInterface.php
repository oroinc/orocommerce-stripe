<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action;

use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Interface for the Stripe WebhookEndpoints API action model.
 */
interface StripeWebhookEndpointActionInterface
{
    public function getActionName(): string;

    public function getStripeClientConfig(): StripeClientConfigInterface;

    public function getStripeWebhookConfig(): StripeWebhookEndpointConfigInterface;
}
