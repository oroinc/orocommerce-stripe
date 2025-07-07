<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeAmountConverter;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Stripe amount converter covering the currencies that don't follow Stripe's standard decimal places rules.
 * The number of decimal places and applicable currencies are configurable.
 *
 * @link https://docs.stripe.com/currencies#special-cases
 */
class ConfigurableDecimalPlacesStripeAmountConverter implements StripeAmountConverterInterface
{
    private readonly array $applicableCurrencies;

    private int $roundingMode = RoundingMode::HALF_UP;

    public function __construct(array $applicableCurrencies, private readonly int $decimalPlaces)
    {
        $this->applicableCurrencies = array_map('strtoupper', $applicableCurrencies);
    }

    public function setRoundingMode(int $roundingMode): void
    {
        $this->roundingMode = $roundingMode;
    }

    #[\Override]
    public function isApplicable(string $currency): bool
    {
        $currency = strtoupper($currency);

        return in_array($currency, $this->applicableCurrencies, true);
    }

    #[\Override]
    public function convertToStripeFormat(
        float $amount,
        string $currency,
        ?string $localeCode = null
    ): int {
        $this->assertApplicableCurrency($currency);

        $bigDecimalAmount = BigDecimal::of($amount);

        $this->assertAmountValid($bigDecimalAmount);

        $multiplier = BigDecimal::of(10)->power($this->decimalPlaces);

        return $bigDecimalAmount
            ->multipliedBy($multiplier)
            ->toScale(0, $this->roundingMode)
            ->toInt();
    }

    private function assertApplicableCurrency(string $currency): void
    {
        if (!$this->isApplicable($currency)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'This converter only handles the following currencies - "%s", got "%s"',
                    implode('", "', $this->applicableCurrencies),
                    $currency
                )
            );
        }
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
        $this->assertApplicableCurrency($currency);

        $divisor = BigDecimal::of(10)->power($this->decimalPlaces);

        return BigDecimal::of($amount)
            ->dividedBy($divisor, $this->decimalPlaces)
            ->toFloat();
    }
}
