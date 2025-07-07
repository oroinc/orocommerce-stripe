<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeAmountConverter;

use Brick\Math\RoundingMode;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\ConfigurableDecimalPlacesStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use PHPUnit\Framework\TestCase;

final class ConfigurableDecimalPlacesStripeAmountConverterTest extends TestCase
{
    private const array APPLICABLE_CURRENCIES = ['USD', 'EUR'];
    private const int DECIMAL_PLACES = 2;

    private ConfigurableDecimalPlacesStripeAmountConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ConfigurableDecimalPlacesStripeAmountConverter(
            self::APPLICABLE_CURRENCIES,
            self::DECIMAL_PLACES
        );
    }

    public function testImplementsStripeAmountConverterInterface(): void
    {
        self::assertInstanceOf(StripeAmountConverterInterface::class, $this->converter);
    }

    public function testConstructorNormalizesCurrenciesToUpperCase(): void
    {
        $converter = new ConfigurableDecimalPlacesStripeAmountConverter(['UsD', 'eUr'], self::DECIMAL_PLACES);

        self::assertTrue($converter->isApplicable('USD'));
        self::assertTrue($converter->isApplicable('EUR'));
    }

    /**
     * @dataProvider isApplicableDataProvider
     */
    public function testIsApplicable(string $currency, bool $expected): void
    {
        self::assertSame($expected, $this->converter->isApplicable($currency));
    }

    public function isApplicableDataProvider(): array
    {
        return [
            'empty' => ['', false],
            'USD uppercase' => ['USD', true],
            'usd lowercase' => ['usd', true],
            'Eur capitalized' => ['Eur', true],
            'JPY not applicable' => ['JPY', false],
        ];
    }

    /**
     * @dataProvider convertToStripeFormatDataProvider
     */
    public function testConvertToStripeFormatWithDefaultRounding(float $amount, int $expected): void
    {
        self::assertSame(
            $expected,
            $this->converter->convertToStripeFormat($amount, 'USD')
        );
    }

    public function convertToStripeFormatDataProvider(): array
    {
        return [
            'zero' => [0.0, 0],
            'integer' => [1.0, 100],
            'two decimals' => [1.23, 123],
            'rounding down' => [1.234, 123],
            'rounding up' => [1.235, 124],
            'large number' => [1234.56, 123456],
            'maximum precision' => [9999.99, 999999],
        ];
    }

    /**
     * @dataProvider roundingModeDataProvider
     */
    public function testConvertToStripeFormatWithDifferentRoundingModes(
        int $roundingMode,
        float $amount,
        int $expected
    ): void {
        $this->converter->setRoundingMode($roundingMode);
        self::assertSame(
            $expected,
            $this->converter->convertToStripeFormat($amount, 'USD')
        );
    }

    public function roundingModeDataProvider(): array
    {
        return [
            'round down' => [RoundingMode::DOWN, 1.239, 123],
            'round up' => [RoundingMode::UP, 1.231, 124],
            'half down' => [RoundingMode::HALF_DOWN, 1.235, 123],
            'half up' => [RoundingMode::HALF_UP, 1.235, 124],
            'half even (round down to even)' => [RoundingMode::HALF_EVEN, 1.225, 122],
            'half even (round up to even)' => [RoundingMode::HALF_EVEN, 1.235, 124],
        ];
    }

    public function testConvertToStripeFormatThrowsExceptionOnNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Negative amounts not allowed, got -1.23');

        $this->converter->convertToStripeFormat(-1.23, 'USD');
    }

    /**
     * @dataProvider convertFromStripeFormatDataProvider
     */
    public function testConvertFromStripeFormat(int $amount, float $expected): void
    {
        self::assertSame(
            $expected,
            $this->converter->convertFromStripeFormat($amount, 'USD')
        );
    }

    public function convertFromStripeFormatDataProvider(): array
    {
        return [
            'zero' => [0, 0.0],
            'integer' => [100, 1.0],
            'two decimals' => [123, 1.23],
            'multiple digits' => [1234, 12.34],
            'large number' => [123456, 1234.56],
            'maximum precision' => [999999, 9999.99],
        ];
    }
}
