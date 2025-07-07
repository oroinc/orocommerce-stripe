<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Configuration;

use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentConfigurationTest extends TestCase
{
    public function testImplementsRequiredInterfaces(): void
    {
        $config = new StripePaymentConfiguration([]);
        self::assertInstanceOf(ResetInterface::class, $config);
    }

    public function testGetPaymentMethodTypesWithManualCaptureWithEmptyConfig(): void
    {
        $config = new StripePaymentConfiguration([]);
        self::assertSame([], $config->getPaymentMethodTypesWithManualCapture());
    }

    public function testGetPaymentMethodTypesWithManualCaptureWithNoManualCapture(): void
    {
        $config = new StripePaymentConfiguration([
            'payment_method_types' => [
                'card' => ['manual_capture' => false],
                'paypal' => ['other_setting' => true],
            ],
        ]);
        self::assertSame([], $config->getPaymentMethodTypesWithManualCapture());
    }

    public function testGetPaymentMethodTypesWithManualCaptureWithManualCapture(): void
    {
        $config = new StripePaymentConfiguration([
            'payment_method_types' => [
                'card' => ['manual_capture' => true],
                'paypal' => ['manual_capture' => false],
                'amazon_pay' => ['manual_capture' => true],
            ],
        ]);
        self::assertEqualsCanonicalizing(
            ['card', 'amazon_pay'],
            $config->getPaymentMethodTypesWithManualCapture()
        );
    }

    public function testGetPaymentMethodTypesWithManualCaptureCachesResult(): void
    {
        $config = new StripePaymentConfiguration([
            'payment_method_types' => ['card' => ['manual_capture' => true]],
        ]);

        $firstCall = $config->getPaymentMethodTypesWithManualCapture();
        $secondCall = $config->getPaymentMethodTypesWithManualCapture();

        self::assertSame($firstCall, $secondCall);
    }

    public function testGetChargeAmountMinimumLimitWithNoConfig(): void
    {
        $config = new StripePaymentConfiguration([]);
        self::assertNull($config->getChargeAmountMinimumLimit('USD'));
    }

    public function testGetChargeAmountMinimumLimitWithCurrencySpecific(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'minimum' => [
                    'USD' => 0.5,
                    '*' => 1.0,
                ],
            ],
        ]);
        self::assertSame(0.5, $config->getChargeAmountMinimumLimit('USD'));
    }

    public function testGetChargeAmountMinimumLimitWithWildcard(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'minimum' => [
                    '*' => 1.0,
                ],
            ],
        ]);
        self::assertSame(1.0, $config->getChargeAmountMinimumLimit('EUR'));
    }

    public function testGetChargeAmountMaximumLimitWithNoConfig(): void
    {
        $config = new StripePaymentConfiguration([]);
        self::assertNull($config->getChargeAmountMaximumLimit('USD'));
    }

    public function testGetChargeAmountMaximumLimitWithCurrencySpecific(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'maximum' => [
                    'USD' => 10000.0,
                    '*' => 5000.0,
                ],
            ],
        ]);
        self::assertSame(10000.0, $config->getChargeAmountMaximumLimit('USD'));
    }

    public function testGetChargeAmountMaximumLimitWithWildcard(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'maximum' => [
                    '*' => 5000.0,
                ],
            ],
        ]);
        self::assertSame(5000.0, $config->getChargeAmountMaximumLimit('EUR'));
    }

    public function testGetCurrenciesByDecimalPlacesWithEmptyConfig(): void
    {
        $config = new StripePaymentConfiguration([]);
        self::assertSame([], $config->getCurrenciesByDecimalPlaces(2));
    }

    public function testGetCurrenciesByDecimalPlacesWithMatchingDecimalPlaces(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'decimal_places' => [
                    'USD' => 2,
                    'JPY' => 0,
                    'EUR' => ['decimal_places' => 2],
                    'BHD' => ['decimal_places' => 3],
                ],
            ],
        ]);

        self::assertEqualsCanonicalizing(
            ['USD', 'EUR'],
            $config->getCurrenciesByDecimalPlaces(2)
        );
    }

    public function testGetCurrenciesByDecimalPlacesWithFractionless(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'decimal_places' => [
                    'USD' => ['decimal_places' => 2, 'fractionless' => false],
                    'JPY' => ['decimal_places' => 0, 'fractionless' => true],
                    'KRW' => ['decimal_places' => 0, 'fractionless' => false],
                ],
            ],
        ]);
        self::assertEqualsCanonicalizing(
            ['JPY'],
            $config->getCurrenciesByDecimalPlaces(0, true)
        );
    }

    public function testGetCurrenciesByDecimalPlacesCachesResult(): void
    {
        $config = new StripePaymentConfiguration([
            'charge_amount' => [
                'decimal_places' => ['USD' => 2],
            ],
        ]);

        $firstCall = $config->getCurrenciesByDecimalPlaces(2);
        $secondCall = $config->getCurrenciesByDecimalPlaces(2);

        self::assertSame($firstCall, $secondCall);
    }

    public function testGetBundleConfig(): void
    {
        $bundleConfig = ['test' => 'value'];
        $config = new StripePaymentConfiguration($bundleConfig);
        self::assertSame($bundleConfig, $config->getBundleConfig());
    }

    public function testResetClearsCache(): void
    {
        $config = new StripePaymentConfiguration([
            'payment_method_types' => ['card' => ['manual_capture' => true]],
            'charge_amount' => ['decimal_places' => ['USD' => 2]],
        ]);

        // Populate caches
        $config->getPaymentMethodTypesWithManualCapture();
        $config->getCurrenciesByDecimalPlaces(2);

        $config->reset();

        // Verify caches are cleared
        self::assertNull(ReflectionUtil::getPropertyValue($config, 'paymentMethodTypesWithManualCapture'));
        self::assertSame([], ReflectionUtil::getPropertyValue($config, 'currenciesByDecimalPlaces'));
    }
}
