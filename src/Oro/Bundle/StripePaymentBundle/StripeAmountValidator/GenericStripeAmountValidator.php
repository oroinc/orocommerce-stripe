<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeAmountValidator;

use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;

/**
 * Validates Stripe amounts against configurable limits.
 *
 * @link https://docs.stripe.com/currencies#minimum-and-maximum-charge-amounts
 */
class GenericStripeAmountValidator implements StripeAmountValidatorInterface
{
    public function __construct(private readonly StripePaymentConfiguration $stripePaymentConfiguration)
    {
    }

    #[\Override]
    public function isAboveMinimum(float $amount, string $currency): bool
    {
        $minAmount = $this->stripePaymentConfiguration->getChargeAmountMinimumLimit($currency);
        if ($minAmount === null) {
            return true;
        }

        return $amount >= $minAmount;
    }

    #[\Override]
    public function isBelowMaximum(float $amount, string $currency): bool
    {
        $maxAmount = $this->stripePaymentConfiguration->getChargeAmountMaximumLimit($currency);
        if ($maxAmount === null) {
            return true;
        }

        return $amount <= $maxAmount;
    }
}
