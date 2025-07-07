<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result;

use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\WebhookEndpoint as StripeWebhookEndpoint;

/**
 * Stripe WebhookEndpoints API action result model.
 */
class StripeWebhookEndpointActionResult implements StripeWebhookEndpointActionResultInterface
{
    public function __construct(
        private readonly bool $successful,
        private readonly ?StripeWebhookEndpoint $stripeWebhookEndpoint = null,
        private readonly ?StripeExceptionInterface $stripeError = null
    ) {
    }

    #[\Override]
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    #[\Override]
    public function getStripeObject(): ?StripeWebhookEndpoint
    {
        return $this->stripeWebhookEndpoint;
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
