<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeAmountConverter;

/**
 * Composite Stripe amount converter that delegates conversion to appropriate inner converters.
 */
class StripeAmountConverterComposite implements StripeAmountConverterInterface
{
    /**
     * @param iterable<StripeAmountConverterInterface> $innerConverters
     */
    public function __construct(private readonly iterable $innerConverters)
    {
    }

    #[\Override]
    public function isApplicable(string $currency): bool
    {
        foreach ($this->innerConverters as $stripeAmountConverter) {
            if ($stripeAmountConverter->isApplicable($currency)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function convertToStripeFormat(float $amount, string $currency, ?string $localeCode = null): int
    {
        foreach ($this->innerConverters as $stripeAmountConverter) {
            if ($stripeAmountConverter->isApplicable($currency)) {
                return $stripeAmountConverter->convertToStripeFormat($amount, $currency, $localeCode);
            }
        }

        throw new \LogicException(sprintf('No converter found for currency "%s"', $currency));
    }

    #[\Override]
    public function convertFromStripeFormat(int $amount, string $currency, ?string $localeCode = null): float
    {
        foreach ($this->innerConverters as $stripeAmountConverter) {
            if ($stripeAmountConverter->isApplicable($currency)) {
                return $stripeAmountConverter->convertFromStripeFormat($amount, $currency, $localeCode);
            }
        }

        throw new \LogicException(sprintf('No converter found for currency "%s"', $currency));
    }
}
