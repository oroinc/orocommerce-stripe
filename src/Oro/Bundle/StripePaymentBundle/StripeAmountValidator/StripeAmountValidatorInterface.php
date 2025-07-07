<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeAmountValidator;

/**
 * Interface for validating whether a Stripe charge amount is within the supported
 * minimum and maximum limits for a given currency.
 */
interface StripeAmountValidatorInterface
{
    /**
     * Checks if the specified amount is equal to or greater than Stripe's
     * minimum allowed charge amount for the given currency.
     *
     * @param float $amount The amount to validate.
     * @param string $currency The ISO-4217 currency code (e.g., 'USD', 'EUR', 'JPY').
     *
     * @return bool True if the amount is above or equal to the minimum limit, false otherwise.
     */
    public function isAboveMinimum(float $amount, string $currency): bool;

    /**
     * Checks if the specified amount is equal to or less than Stripe's
     * maximum allowed charge amount for the given currency.
     *
     * @param float $amount The amount to validate.
     * @param string $currency The ISO-4217 currency code (e.g., 'USD', 'EUR', 'JPY').
     *
     * @return bool True if the amount is below or equal to the maximum limit, false otherwise.
     */
    public function isBelowMaximum(float $amount, string $currency): bool;
}
