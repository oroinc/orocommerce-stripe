<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeAmountConverter;

use Brick\Math\RoundingMode;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\GenericStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use PHPUnit\Framework\TestCase;

final class GenericStripeAmountConverterTest extends TestCase
{
    public function testImplementsStripeAmountConverterInterface(): void
    {
        self::assertInstanceOf(StripeAmountConverterInterface::class, new GenericStripeAmountConverter());
    }

    /**
     * @dataProvider isApplicableDataProvider
     */
    public function testIsApplicable(string $currency): void
    {
        $converter = new GenericStripeAmountConverter();
        self::assertTrue($converter->isApplicable($currency));
    }

    public function isApplicableDataProvider(): array
    {
        return [
            ['USD'],
            ['EUR'],
            ['JPY'],
        ];
    }

    /**
     * @dataProvider convertToStripeFormatDataProvider
     */
    public function testConvertToStripeFormat(
        float $amount,
        string $currency,
        ?string $localeCode,
        int $expected
    ): void {
        $converter = new GenericStripeAmountConverter();
        self::assertSame($expected, $converter->convertToStripeFormat($amount, $currency, $localeCode));
    }

    public function convertToStripeFormatDataProvider(): array
    {
        return [
            'USD default locale' => [10.50, 'USD', null, 1050],
            'USD en_US locale' => [10.50, 'USD', 'en_US', 1050],
            'EUR de_DE locale' => [10.50, 'EUR', 'de_DE', 1050],
            'JPY ja_JP locale zero decimal' => [10.50, 'JPY', 'ja_JP', 11],
            'BHD ar_BH locale 3 decimal places' => [10.123, 'BHD', 'ar_BH', 10123],
            'USD large amount' => [999999.99, 'USD', null, 99999999],
            'USD zero amount' => [0.0, 'USD', null, 0],
        ];
    }

    /**
     * @dataProvider convertToStripeFormatWithRoundingDataProvider
     */
    public function testConvertToStripeFormatWithRounding(
        int $roundingMode,
        float $amount,
        int $expected
    ): void {
        $converter = new GenericStripeAmountConverter();
        $converter->setRoundingMode($roundingMode);

        self::assertSame($expected, $converter->convertToStripeFormat($amount, 'USD'));
    }

    public function convertToStripeFormatWithRoundingDataProvider(): array
    {
        return [
            'rounding up' => [RoundingMode::UP, 0.005, 1],
            'rounding down' => [RoundingMode::DOWN, 0.005, 0],
            'half up' => [RoundingMode::HALF_UP, 0.005, 1],
            'half down' => [RoundingMode::HALF_DOWN, 0.005, 0],
        ];
    }

    /**
     * @dataProvider negativeConvertToStripeFormatDataProvider
     */
    public function testConvertToStripeFormatWithNegativeAmount(float $amount): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Negative amounts not allowed');

        $converter = new GenericStripeAmountConverter();
        $converter->convertToStripeFormat($amount, 'USD');
    }

    public function negativeConvertToStripeFormatDataProvider(): array
    {
        return [
            'negative amount' => [-10.50],
            'small negative amount' => [-0.01],
        ];
    }

    /**
     * @dataProvider convertFromStripeFormatDataProvider
     */
    public function testConvertFromStripeFormat(
        int $amount,
        string $currency,
        ?string $localeCode,
        float $expected
    ): void {
        $converter = new GenericStripeAmountConverter();
        self::assertSame($expected, $converter->convertFromStripeFormat($amount, $currency, $localeCode));
    }

    public function convertFromStripeFormatDataProvider(): array
    {
        return [
            'USD default locale' => [1050, 'USD', null, 10.50],
            'USD en_US locale' => [1050, 'USD', 'en_US', 10.50],
            'EUR de_DE locale' => [1050, 'EUR', 'de_DE', 10.50],
            'JPY ja_JP locale zero decimal' => [11, 'JPY', 'ja_JP', 11.0],
            'BHD ar_BH locale 3 decimal places' => [10123, 'BHD', 'ar_BH', 10.123],
            'USD large amount' => [99999999, 'USD', null, 999999.99],
            'USD small amount' => [1, 'USD', null, 0.01],
            'USD zero amount' => [0, 'USD', null, 0.0],
        ];
    }
}
