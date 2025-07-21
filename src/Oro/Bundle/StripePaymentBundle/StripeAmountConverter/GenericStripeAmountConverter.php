<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeAmountConverter;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Generic Stripe amount converter.
 * Makes use of \NumberFormatter to get number of decimal places allowed for a currency.
 *
 * @link https://docs.stripe.com/currencies
 */
class GenericStripeAmountConverter implements StripeAmountConverterInterface
{
    private int $roundingMode = RoundingMode::HALF_UP;

    public function setRoundingMode(int $roundingMode): void
    {
        $this->roundingMode = $roundingMode;
    }

    #[\Override]
    public function isApplicable(string $currency): bool
    {
        return true;
    }

    #[\Override]
    public function convertToStripeFormat(
        float $amount,
        string $currency,
        ?string $localeCode = null
    ): int {
        $currency = strtoupper($currency);
        $decimalPlaces = $this->getDecimalPlaces($currency, $localeCode);

        $bigDecimalAmount = BigDecimal::of($amount);

        $this->assertAmountValid($bigDecimalAmount);

        $multiplier = BigDecimal::of(10)->power($decimalPlaces);

        return $bigDecimalAmount
            ->multipliedBy($multiplier)
            ->toScale(0, $this->roundingMode)
            ->toInt();
    }

    private function assertAmountValid(BigDecimal $bigDecimalAmount): void
    {
        if ($bigDecimalAmount->isNegative()) {
            throw new \InvalidArgumentException('Negative amounts not allowed, got ' . $bigDecimalAmount->toFloat());
        }
    }

    #[\Override]
    public function convertFromStripeFormat(int $amount, string $currency, ?string $localeCode = null): float
    {
        $currency = strtoupper($currency);
        $decimalPlaces = $this->getDecimalPlaces($currency, $localeCode);

        $divisor = BigDecimal::of(10)->power($decimalPlaces);

        return BigDecimal::of($amount)
            ->dividedBy($divisor, $decimalPlaces, $this->roundingMode)
            ->toFloat();
    }

    private function getDecimalPlaces(string $currencyCode, ?string $localeCode = null): int
    {
        $locale = sprintf('%s@currency=%s', $localeCode ?? \Locale::getDefault(), $currencyCode);

        return (new \NumberFormatter($locale, \NumberFormatter::CURRENCY))
            ->getAttribute(\NumberFormatter::MIN_FRACTION_DIGITS);
    }
}
