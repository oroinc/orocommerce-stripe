<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeAmountConverter;

use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\FractionLessTwoDecimalStripeAmountConverter;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class FractionLessTwoDecimalStripeAmountConverterTest extends TestCase
{
    private const array APPLICABLE_CURRENCIES = ['ISK', 'UGX'];

    private FractionLessTwoDecimalStripeAmountConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new FractionLessTwoDecimalStripeAmountConverter(self::APPLICABLE_CURRENCIES);
    }

    public function testImplementsStripeAmountConverterInterface(): void
    {
        self::assertInstanceOf(StripeAmountConverterInterface::class, $this->converter);
    }

    public function testConstructorNormalizesCurrencyCodesToUpperCase(): void
    {
        $converter = new FractionLessTwoDecimalStripeAmountConverter(['isk', 'UgX']);

        self::assertTrue($converter->isApplicable('ISK'));
        self::assertTrue($converter->isApplicable('UGX'));
        self::assertFalse($converter->isApplicable('JPY'));
    }

    /**
     * @dataProvider applicableCurrencyProvider
     */
    public function testIsApplicableReturnsTrueWhenSupportedCurrencies(string $currency): void
    {
        self::assertTrue($this->converter->isApplicable($currency));
    }

    public function applicableCurrencyProvider(): array
    {
        return [
            'ISK uppercase' => ['ISK'],
            'ISK lowercase' => ['isk'],
            'UGX uppercase' => ['UGX'],
            'UGX lowercase' => ['ugx'],
        ];
    }

    /**
     * @dataProvider unsupportedCurrencyProvider
     */
    public function testIsApplicableReturnsFalseWhenUnsupportedCurrencies(string $currency): void
    {
        self::assertFalse($this->converter->isApplicable($currency));
    }

    public function unsupportedCurrencyProvider(): array
    {
        return [
            'empty' => [''],
            'USD' => ['USD'],
            'EUR' => ['EUR'],
            'JPY' => ['JPY'],
        ];
    }

    /**
     * @dataProvider convertToStripeFormatWithValidAmountsProvider
     */
    public function testConvertToStripeFormatWithValidAmounts(
        float $amount,
        int $expected,
        string $currency
    ): void {
        self::assertSame($expected, $this->converter->convertToStripeFormat($amount, $currency));
    }

    public function convertToStripeFormatWithValidAmountsProvider(): array
    {
        return [
            'ISK whole number' => [10, 1000, 'ISK'],
            'ISK zero' => [0, 0, 'ISK'],
            'ISK large number' => [1234, 123400, 'ISK'],
            'ISK with .00' => [10.00, 1000, 'ISK'],
            'UGX whole number' => [50, 5000, 'UGX'],
            'UGX with .00' => [50.00, 5000, 'UGX'],
        ];
    }

    /**
     * @dataProvider convertToStripeFormatThrowsExceptionForInvalidAmountsProvider
     */
    public function testConvertToStripeFormatThrowsExceptionForInvalidAmounts(
        float $invalidAmount,
        string $expectedMessage
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        $this->converter->convertToStripeFormat($invalidAmount, 'ISK');
    }

    public function convertToStripeFormatThrowsExceptionForInvalidAmountsProvider(): array
    {
        return [
            'negative amount' => [
                -10,
                'Negative amounts not allowed, got -10',
            ],
            'fractional amount' => [
                10.5,
                'Amounts must be a whole number (e.g., "10" or "10.00"), got 10.5',
            ],
            'small fractional amount' => [
                10.01,
                'Amounts must be a whole number (e.g., "10" or "10.00"), got 10.01',
            ],
        ];
    }

    /**
     * @dataProvider convertToStripeFormatThrowsExceptionForUnsupportedCurrencyProvider
     */
    public function testConvertToStripeFormatThrowsExceptionForUnsupportedCurrency(string $unsupportedCurrency): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'This converter only handles the following currencies - "%s", got "%s"',
                implode('", "', self::APPLICABLE_CURRENCIES),
                strtoupper($unsupportedCurrency)
            )
        );

        $this->converter->convertToStripeFormat(10, $unsupportedCurrency);
    }

    public function convertToStripeFormatThrowsExceptionForUnsupportedCurrencyProvider(): array
    {
        return [
            'empty string' => [''],
            'USD' => ['USD'],
            'EUR' => ['EUR'],
            'JPY' => ['JPY'],
        ];
    }

    /**
     * @dataProvider convertFromStripeFormatProvider
     */
    public function testConvertFromStripeFormat(
        int $stripeAmount,
        float $expected,
        string $currency
    ): void {
        self::assertSame($expected, $this->converter->convertFromStripeFormat($stripeAmount, $currency));
    }

    public function convertFromStripeFormatProvider(): array
    {
        return [
            'ISK basic conversion' => [1000, 10.00, 'ISK'],
            'ISK zero' => [0, 0.00, 'ISK'],
            'ISK large number' => [123456, 1234.56, 'ISK'],
            'ISK small amount' => [1, 0.01, 'ISK'],
            'UGX basic conversion' => [5000, 50.00, 'UGX'],
            'UGX small amount' => [50, 0.50, 'UGX'],
        ];
    }

    /**
     * @dataProvider convertFromStripeFormatThrowsExceptionForUnsupportedCurrencyProvider
     */
    public function testConvertFromStripeFormatThrowsExceptionForUnsupportedCurrency(string $unsupportedCurrency): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(
                'This converter only handles the following currencies - "%s", got "%s"',
                implode('", "', self::APPLICABLE_CURRENCIES),
                strtoupper($unsupportedCurrency)
            )
        );

        $this->converter->convertFromStripeFormat(1000, $unsupportedCurrency);
    }

    public function convertFromStripeFormatThrowsExceptionForUnsupportedCurrencyProvider(): array
    {
        return [
            'empty string' => [''],
            'USD' => ['USD'],
            'EUR' => ['EUR'],
            'JPY' => ['JPY'],
        ];
    }
}
