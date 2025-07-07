<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Configuration;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The main entry point for accessing OroStripePaymentBundle configuration.
 */
class StripePaymentConfiguration implements ResetInterface
{
    private ?array $paymentMethodTypesWithManualCapture = null;

    private array $currenciesByDecimalPlaces = [];

    public function __construct(private readonly array $bundleConfig)
    {
    }

    /**
     * Gets all payment method types that support manual capture.
     *
     * @return array<string> List of payment method types (e.g. ['card', 'amazon_pay'])
     */
    public function getPaymentMethodTypesWithManualCapture(): array
    {
        if ($this->paymentMethodTypesWithManualCapture !== null) {
            return $this->paymentMethodTypesWithManualCapture;
        }

        $this->paymentMethodTypesWithManualCapture = [];
        foreach ($this->bundleConfig['payment_method_types'] ?? [] as $type => $config) {
            if ($config['manual_capture'] ?? false) {
                $this->paymentMethodTypesWithManualCapture[] = $type;
            }
        }

        return $this->paymentMethodTypesWithManualCapture;
    }

    public function getChargeAmountMinimumLimit(string $currency): ?float
    {
        return $this->bundleConfig['charge_amount']['minimum'][$currency]
            ?? $this->bundleConfig['charge_amount']['minimum']['*']
            ?? null;
    }

    public function getChargeAmountMaximumLimit(string $currency): ?float
    {
        return $this->bundleConfig['charge_amount']['maximum'][$currency]
            ?? $this->bundleConfig['charge_amount']['maximum']['*']
            ?? null;
    }

    /**
     * Gets all currencies matching specific decimal places configuration.
     *
     * @param int $decimalPlaces Number of decimal places to filter by.
     * @param bool $fractionless Whether to match fraction-less currencies.
     *
     * @return array<string> List of ISO-4217 currency codes (e.g., 'USD', 'EUR', 'JPY') that match the criteria.
     */
    public function getCurrenciesByDecimalPlaces(int $decimalPlaces, bool $fractionless = false): array
    {
        if (!isset($this->currenciesByDecimalPlaces[$decimalPlaces][(int)$fractionless])) {
            foreach ($this->bundleConfig['charge_amount']['decimal_places'] ?? [] as $currency => $config) {
                // Normalizes config to handle both shorthand and full format.
                $config = is_array($config) ? $config : ['decimal_places' => $config];

                if ($config['decimal_places'] !== $decimalPlaces) {
                    continue;
                }

                if (($config['fractionless'] ?? false) !== $fractionless) {
                    continue;
                }

                $this->currenciesByDecimalPlaces[$decimalPlaces][(int)$fractionless][] = $currency;
            }
        }

        return $this->currenciesByDecimalPlaces[$decimalPlaces][(int)$fractionless] ?? [];
    }

    public function getBundleConfig(): array
    {
        return $this->bundleConfig;
    }

    #[\Override]
    public function reset(): void
    {
        $this->paymentMethodTypesWithManualCapture = null;
        $this->currenciesByDecimalPlaces = [];
    }
}
