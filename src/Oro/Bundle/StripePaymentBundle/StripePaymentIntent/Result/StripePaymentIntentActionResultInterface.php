<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result;

use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\StripeObject;

/**
 * Interface for the Stripe PaymentIntents API action result model.
 */
interface StripePaymentIntentActionResultInterface
{
    public function isSuccessful(): bool;

    public function getStripeObject(): ?StripeObject;

    public function getStripeError(): ?StripeExceptionInterface;

    public function toArray(): array;
}
