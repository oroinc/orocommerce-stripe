<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action;

/**
 * Interface for configuration model for working with Stripe PaymentIntents API.
 */
interface StripePaymentIntentConfigInterface
{
    /**
     * @return string Payment intent capture method: 'automatic', 'automatic_async', 'manual'
     *
     * @link https://docs.stripe.com/api/payment_intents/create#create_payment_intent-capture_method
     */
    public function getCaptureMethod(): string;

    /**
     * @return array<string> List of payment method types capable of manual capture. (e.g. ['card', 'amazon_pay'])
     */
    public function getPaymentMethodTypesWithManualCapture(): array;

    /**
     * @return bool True if a payment authorization hold must be re-authorized
     *  when the authorization window is about to expire.
     */
    public function isReAuthorizationEnabled(): bool;
}
