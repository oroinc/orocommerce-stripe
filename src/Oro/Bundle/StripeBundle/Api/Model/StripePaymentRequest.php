<?php

namespace Oro\Bundle\StripeBundle\Api\Model;

/**
 * Represents the Stripe payment request.
 */
final class StripePaymentRequest
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
