<?php

namespace Oro\Bundle\StripePaymentBundle\StripeAmountConverter;

use Brick\Math\BigDecimal;

/**
 * Stripe amount converter for currencies that require special decimal place handling.
 *
 * This converter handles currencies that:
 * 1. Are technically defined with 2 decimal places in Stripe's system
 * 2. But are actually used without fractions in practice (whole amounts only)
 *
 * Stripe indicates there are 2 currencies that are two-decimal but still fraction-less: ISK and UGX.
 * For reference, here are the Stripe requirements for ISK:
 *
 *  ISK transitioned to a zero-decimal currency, but backwards compatibility requires you to represent it
 *  as a two-decimal value, where the decimal amount is always 00. For example, to charge 5 ISK, provide
 *  an amount value of 500. You can’t charge fractions of ISK.
 *
 * @link https://docs.stripe.com/currencies#special-cases
 */
class FractionLessTwoDecimalStripeAmountConverter implements StripeAmountConverterInterface
{
    private readonly array $applicableCurrencies;

    public function __construct(array $applicableCurrencies)
    {
        $this->applicableCurrencies = array_map('strtoupper', $applicableCurrencies);
    }

    #[\Override]
    public function isApplicable(string $currency): bool
    {
        $currency = strtoupper($currency);

        return in_array($currency, $this->applicableCurrencies, true);
    }

    #[\Override]
    public function convertToStripeFormat(float $amount, string $currency, ?string $localeCode = null): int
    {
        $this->assertApplicableCurrency($currency);

        $bigDecimalAmount = BigDecimal::of($amount);

        $this->assertAmountValid($bigDecimalAmount);

        return $bigDecimalAmount
            ->multipliedBy(100)
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

        if (!$this->isWholeNumberAmount($bigDecimalAmount)) {
            throw new \InvalidArgumentException(
                'Amounts must be a whole number (e.g., "10" or "10.00"), got ' . $bigDecimalAmount->toFloat()
            );
        }
    }

    private function isWholeNumberAmount(BigDecimal $bigDecimalAmount): bool
    {
        // Allow either integer values or exactly two zero decimal places.
        return $bigDecimalAmount->getScale() === 0 ||
            ($bigDecimalAmount->getScale() === 2 && $bigDecimalAmount->getFractionalPart() === '00');
    }

    #[\Override]
    public function convertFromStripeFormat(int $amount, string $currency, ?string $localeCode = null): float
    {
        $this->assertApplicableCurrency($currency);

        return BigDecimal::of($amount)
            ->dividedBy(100, 2)
            ->toFloat();
    }
}
