<?php

namespace Oro\Bundle\StripeBundle\Api\Model;

/**
 * Represents the Stripe payment information request.
 */
final class StripePaymentInfoRequest
{
    private ?string $stripePaymentMethodId = null;

    public function getStripePaymentMethodId(): ?string
    {
        return $this->stripePaymentMethodId;
    }

    public function setStripePaymentMethodId(?string $stripePaymentMethodId): void
    {
        $this->stripePaymentMethodId = $stripePaymentMethodId;
    }
}
