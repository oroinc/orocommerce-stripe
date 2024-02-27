<?php

namespace Oro\Bundle\StripeBundle\Converter;

use Locale;
use NumberFormatter;

/**
 * Converts charge amounts to Stripe format.
 */
class PaymentAmountConverter
{
    /**
     * According to https://stripe.com/docs/api/payment_intents/object#payment_intent_object-amount AMOUNT value
     * should be present in the smallest currency unit.
     */
    public static function convertToStripeFormat(float $amount, string $currency): int
    {
        $digits = self::getFractionDigits($currency);

        return match ($digits) {
            0 => (int)round($amount), // https://docs.stripe.com/currencies#zero-decimal
            2 => (int)round($amount * 100), // https://docs.stripe.com/currencies#presentment-currencies
            3 => (int)round($amount * 1000, -1), // https://docs.stripe.com/currencies#three-decimal
            default => throw new \RuntimeException('Unsupported currency: ' . $currency)
        };
    }

    public static function convertFromStripeFormat(int $amount, string $currency): int|float
    {
        $digits = self::getFractionDigits($currency);

        return match ($digits) {
            0 => $amount, // https://docs.stripe.com/currencies#zero-decimal
            2 => (float)$amount / 100, // https://docs.stripe.com/currencies#presentment-currencies
            3 => (float)$amount / 1000, // https://docs.stripe.com/currencies#three-decimal
            default => throw new \RuntimeException('Unsupported currency: ' . $currency)
        };
    }

    private static function getFractionDigits(string $currencyCode): int
    {
        $locale = sprintf('%s@currency=%s', Locale::getDefault(), $currencyCode);
        $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);

        return $formatter->getAttribute(NumberFormatter::MIN_FRACTION_DIGITS);
    }
}
