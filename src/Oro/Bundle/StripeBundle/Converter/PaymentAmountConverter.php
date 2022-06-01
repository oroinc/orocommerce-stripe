<?php

namespace Oro\Bundle\StripeBundle\Converter;

/**
 * Converts charge amounts to Stripe format.
 */
class PaymentAmountConverter
{
    /**
     * According to https://stripe.com/docs/api/payment_intents/object#payment_intent_object-amount AMOUNT value
     * should be present in the smallest currency unit.
     */
    public static function convertToStripeFormat(float $amount): int
    {
        //Round multiplied result before conversion to int to prevent wrong conversion result.
        //For ex. float 3298.0 converts to 3297 as integer value.
        return (int) round($amount * 100, 0);
    }

    public static function convertFromStripeFormat(int $amount): float
    {
        return (float) $amount / 100;
    }
}
