<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Result;

use Stripe\Customer as StripeCustomer;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;

/**
 * Stripe Customers API action result model.
 */
class StripeCustomerActionResult implements StripeCustomerActionResultInterface
{
    public function __construct(
        private readonly bool $successful,
        private readonly ?StripeCustomer $stripeCustomer = null,
        private readonly ?StripeExceptionInterface $stripeError = null
    ) {
    }

    #[\Override]
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    #[\Override]
    public function getStripeObject(): ?StripeCustomer
    {
        return $this->stripeCustomer;
    }

    #[\Override]
    public function getStripeError(): ?StripeExceptionInterface
    {
        return $this->stripeError;
    }

    #[\Override]
    public function toArray(): array
    {
        $array = [
            'successful' => $this->isSuccessful(),
        ];

        if ($this->stripeError !== null) {
            $array['error'] = $this->stripeError->getMessage();
        }

        return $array;
    }
}
