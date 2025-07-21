<?php

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResultInterface;

/**
 * Interface for the Stripe WebhookEndpoints API action executor.
 */
interface StripeWebhookEndpointActionExecutorInterface
{
    public function isSupportedByActionName(string $stripeActionName): bool;

    public function isApplicableForAction(StripeWebhookEndpointActionInterface $stripeAction): bool;

    public function executeAction(
        StripeWebhookEndpointActionInterface $stripeAction
    ): StripeWebhookEndpointActionResultInterface;
}
