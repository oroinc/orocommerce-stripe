<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeAmountConverter;

/**
 * Converts the given amount to Stripe format and vice versa.
 *
 * Stripe requires amounts to be in specific formats (usually the smallest currency unit like cents for USD).
 * This interface provides methods for bidirectional conversion between application format (float) and
 * Stripe format (integer).
 *
 * @see https://docs.stripe.com/currencies
 */
interface StripeAmountConverterInterface
{
    /**
     * Checks if the converter supports the given currency.
     *
     * @param string $currency The ISO-4217 3-letter currency code to check (e.g. 'USD', 'EUR').
     *
     * @return bool True if the converter supports the currency, false otherwise.
     */
    public function isApplicable(string $currency): bool;

    /**
     * Converts an amount to Stripe format.
     *
     * @param float $amount The amount to convert (e.g., 10.50 for $10.50).
     * @param string $currency The ISO-4217 3-letter currency code (e.g. 'USD').
     * @param string|null $localeCode Optional locale code for locale-specific formatting (e.g. 'en').
     *
     * @return int The amount in Stripe format (e.g., 1050 for $10.50).
     */
    public function convertToStripeFormat(float $amount, string $currency, ?string $localeCode = null): int;

    /**
     * Converts an amount from Stripe format.
     *
     * @param int $amount The amount in Stripe format (e.g., 1050 for $10.50)
     * @param string $currency The ISO-4217 3-letter currency code (e.g. 'USD')
     * @param string|null $localeCode Optional locale code for locale-specific formatting(e.g. 'en').
     *
     * @return float The converted amount (e.g., 10.50 for 1050).
     */
    public function convertFromStripeFormat(int $amount, string $currency, ?string $localeCode = null): float;
}
