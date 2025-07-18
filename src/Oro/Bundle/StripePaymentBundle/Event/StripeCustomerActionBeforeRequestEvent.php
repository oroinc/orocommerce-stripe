<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Event;

use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;

/**
 * Dispatched right before the Stripe Customers action executor is going to make a request to Stripe API.
 */
class StripeCustomerActionBeforeRequestEvent
{
    public function __construct(
        private readonly StripeCustomerActionInterface $stripeAction,
        private readonly string $requestName,
        private array $requestArgs
    ) {
    }

    public function getStripeAction(): StripeCustomerActionInterface
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
