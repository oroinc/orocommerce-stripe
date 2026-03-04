<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Api\Model;

/**
 * Represents the Stripe Payment Element payment info request.
 * Contains the confirmation token ID and the payment method type required to create a Stripe Payment Intent.
 */
class StripePaymentElementPaymentInfoRequest
{
    private ?string $confirmationTokenId  = null;
    private ?string $paymentMethodType  = null;

    public function getConfirmationTokenId(): ?string
    {
        return $this->confirmationTokenId;
    }

    public function setConfirmationTokenId(?string $confirmationTokenId): void
    {
        $this->confirmationTokenId = $confirmationTokenId;
    }

    public function getPaymentMethodType(): ?string
    {
        return $this->paymentMethodType;
    }

    public function setPaymentMethodType(?string $paymentMethodType): void
    {
        $this->paymentMethodType = $paymentMethodType;
    }
}
