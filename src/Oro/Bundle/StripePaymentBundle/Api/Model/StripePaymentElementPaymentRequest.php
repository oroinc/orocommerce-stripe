<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Api\Model;

/**
 * Represents the Stripe Payment Element payment request.
 * Contains the success, failure and partially paid URLs to redirect the user to - after the payment is processed.
 */
final class StripePaymentElementPaymentRequest
{
    private ?string $successUrl = null;
    private ?string $failureUrl = null;
    private ?string $partiallyPaidUrl = null;

    public function getSuccessUrl(): ?string
    {
        return $this->successUrl;
    }

    public function setSuccessUrl(?string $successUrl): void
    {
        $this->successUrl = $successUrl;
    }

    public function getFailureUrl(): ?string
    {
        return $this->failureUrl;
    }

    public function setFailureUrl(?string $failureUrl): void
    {
        $this->failureUrl = $failureUrl;
    }

    public function getPartiallyPaidUrl(): ?string
    {
        return $this->partiallyPaidUrl;
    }

    public function setPartiallyPaidUrl(?string $partiallyPaidUrl): void
    {
        $this->partiallyPaidUrl = $partiallyPaidUrl;
    }
}
