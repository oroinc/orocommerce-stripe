<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action;

use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Stripe WebhookEndpoints API action model for the delete action.
 */
class DeleteStripeWebhookEndpointAction extends AbstractStripeWebhookEndpointAction
{
    public const string ACTION_NAME = 'webhook_endpoint_delete';

    public function __construct(
        protected StripeClientConfigInterface&StripeWebhookEndpointConfigInterface $stripeWebhookEndpointConfig
    ) {
        parent::__construct(static::ACTION_NAME, $stripeWebhookEndpointConfig);
    }
}
