<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Event;

use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;

/**
 * Dispatched right before the Stripe PaymentIntents API action executor is going to make a request to Stripe API.
 */
class StripePaymentIntentActionBeforeRequestEvent
{
    public function __construct(
        private readonly StripePaymentIntentActionInterface $stripeAction,
        private readonly string $requestName,
        private array $requestArgs
    ) {
    }

    public function getStripeAction(): StripePaymentIntentActionInterface
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
