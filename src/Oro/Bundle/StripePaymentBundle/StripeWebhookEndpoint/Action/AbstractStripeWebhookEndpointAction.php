<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action;

use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Base Stripe WebhookEndpoints API action model.
 */
abstract class AbstractStripeWebhookEndpointAction implements StripeWebhookEndpointActionInterface
{
    public function __construct(
        protected string $actionName,
        protected StripeClientConfigInterface&StripeWebhookEndpointConfigInterface $stripeWebhookEndpointConfig
    ) {
    }

    #[\Override]
    public function getActionName(): string
    {
        return $this->actionName;
    }

    #[\Override]
    public function getStripeClientConfig(): StripeClientConfigInterface
    {
        return $this->stripeWebhookEndpointConfig;
    }

    #[\Override]
    public function getStripeWebhookConfig(): StripeWebhookEndpointConfigInterface
    {
        return $this->stripeWebhookEndpointConfig;
    }
}
