<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Event;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;

/**
 * Dispatched right before the Stripe WebhookEndpoints API action executor is going to make a request to Stripe API.
 */
class StripeWebhookEndpointActionBeforeRequestEvent
{
    public function __construct(
        private readonly StripeWebhookEndpointActionInterface $stripeAction,
        private readonly string $requestName,
        private array $requestArgs
    ) {
    }

    public function getStripeAction(): StripeWebhookEndpointActionInterface
    {
        return $this->stripeAction;
    }

    public function getRequestName(): string
    {
        return $this->requestName;
    }

    public function getRequestArgs(): array
    {
        return $this->requestArgs;
    }

    public function setRequestArgs(array $requestArgs): void
    {
        $this->requestArgs = $requestArgs;
    }
}
