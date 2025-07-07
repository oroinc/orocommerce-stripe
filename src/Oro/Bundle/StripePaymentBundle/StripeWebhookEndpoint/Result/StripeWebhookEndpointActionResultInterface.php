<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result;

use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\StripeObject;

/**
 * Interface for the Stripe WebhookEndpoints API action result model.
 */
interface StripeWebhookEndpointActionResultInterface
{
    public function isSuccessful(): bool;

    public function getStripeObject(): ?StripeObject;

    public function getStripeError(): ?StripeExceptionInterface;

    public function toArray(): array;
}
