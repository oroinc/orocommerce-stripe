<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result;

use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\Refund as StripeRefund;

/**
 * Stripe PaymentIntents API refund action result model.
 */
class StripePaymentIntentRefundActionResult implements StripePaymentIntentActionResultInterface
{
    public function __construct(
        private readonly bool $successful,
        private readonly ?StripeRefund $stripeRefund = null,
        private readonly ?StripeExceptionInterface $stripeError = null
    ) {
    }

    #[\Override]
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    #[\Override]
    public function getStripeObject(): ?StripeRefund
    {
        return $this->stripeRefund;
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
