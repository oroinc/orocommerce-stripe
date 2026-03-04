<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result;

use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;
use Stripe\PaymentIntent as StripePaymentIntent;

/**
 * Stripe PaymentIntents API action result model.
 */
class StripePaymentIntentActionResult implements StripePaymentIntentActionResultInterface
{
    public function __construct(
        private readonly bool $successful,
        private readonly ?StripePaymentIntent $stripePaymentIntent = null,
        private readonly ?StripeExceptionInterface $stripeError = null
    ) {
    }

    #[\Override]
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    #[\Override]
    public function getStripeObject(): ?StripePaymentIntent
    {
        return $this->stripePaymentIntent;
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

        if (isset($this->stripePaymentIntent->status) && $this->stripePaymentIntent->status === 'requires_action') {
            $array['requiresAction'] = true;
        }

        if (isset($this->stripePaymentIntent->client_secret)) {
            $array['paymentIntentClientSecret'] = $this->stripePaymentIntent->client_secret;
        }

        if (isset($this->stripePaymentIntent->last_payment_error)) {
            $array['error'] = $this->stripePaymentIntent->last_payment_error['message'] ?? null;
            $array['errorCode'] = $this->stripePaymentIntent->last_payment_error['code'] ?? null;
            $array['declineCode'] = $this->stripePaymentIntent->last_payment_error['decline_code'] ?? null;
        }

        if ($this->stripeError !== null) {
            $array['error'] = $this->stripeError->getMessage();
            $array['errorCode'] = $this->stripeError->getCode();

            if ($this->stripeError instanceof ApiErrorException) {
                $array['stripeCode'] = $this->stripeError->getStripeCode();
            }

            if ($this->stripeError instanceof CardException) {
                $array['declineCode'] = $this->stripeError->getDeclineCode();
            }
        }

        return $array;
    }
}
