<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Result;

use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\StripeObject;

/**
 * Interface for the Stripe Customers API action result model.
 */
interface StripeCustomerActionResultInterface
{
    public function isSuccessful(): bool;

    public function getStripeObject(): ?StripeObject;

    public function getStripeError(): ?StripeExceptionInterface;

    public function toArray(): array;
}
