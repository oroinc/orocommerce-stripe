<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeAmountValidator;

use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;
use Oro\Bundle\StripePaymentBundle\StripeAmountValidator\GenericStripeAmountValidator;
use PHPUnit\Framework\TestCase;

final class GenericStripeAmountValidatorTest extends TestCase
{
    /**
     * @dataProvider isAboveMinimumDataProvider
     */
    public function testIsAboveMinimum(
        ?float $minAmount,
        float $amount,
        bool $expected
    ): void {
        $currency = 'USD';
        $stripePaymentConfiguration = new StripePaymentConfiguration(
            ['charge_amount' => ['minimum' => [$currency => $minAmount]]]
        );

        $validator = new GenericStripeAmountValidator($stripePaymentConfiguration);
        self::assertSame($expected, $validator->isAboveMinimum($amount, $currency));
    }

    public function isAboveMinimumDataProvider(): array
    {
        return [
            'no minimum limit' => [null, 10.50, true],
            'amount equals minimum' => [10.50, 10.50, true],
            'amount above minimum' => [10.50, 10.51, true],
            'amount below minimum' => [10.50, 10.49, false],
            'zero amount with zero minimum' => [0.0, 0.0, true],
            'small amount above minimum' => [0.01, 0.02, true],
            'small amount below minimum' => [0.01, 0.00, false],
        ];
    }

    /**
     * @dataProvider isBelowMaximumDataProvider
     */
    public function testIsBelowMaximum(
        ?float $maxAmount,
        float $amount,
        bool $expected
    ): void {
        $currency = 'USD';
        $stripePaymentConfiguration = new StripePaymentConfiguration(
            ['charge_amount' => ['maximum' => [$currency => $maxAmount]]]
        );

        $validator = new GenericStripeAmountValidator($stripePaymentConfiguration);
        self::assertSame($expected, $validator->isBelowMaximum($amount, $currency));
    }

    public function isBelowMaximumDataProvider(): array
    {
        return [
            'no maximum limit' => [null, 10.50, true],
            'amount equals maximum' => [10.50, 10.50, true],
            'amount below maximum' => [10.50, 10.49, true],
            'amount above maximum' => [10.50, 10.51, false],
            'large amount with high maximum' => [999999.99, 999999.98, true],
            'large amount above maximum' => [999999.99, 1000000.00, false],
            'zero maximum with zero amount' => [0.0, 0.0, true],
            'zero maximum with positive amount' => [0.0, 0.01, false],
        ];
    }

    /**
     * @dataProvider boundaryConditionsDataProvider
     */
    public function testBoundaryConditions(
        ?float $minAmount,
        ?float $maxAmount,
        float $amount,
        bool $expectedAbove,
        bool $expectedBelow
    ): void {
        $currency = 'USD';
        $stripePaymentConfiguration = new StripePaymentConfiguration(
            [
                'charge_amount' => [
                    'maximum' => [$currency => $maxAmount],
                    'minimum' => [$currency => $minAmount],
                ],
            ]
        );

        $validator = new GenericStripeAmountValidator($stripePaymentConfiguration);

        self::assertSame($expectedAbove, $validator->isAboveMinimum($amount, $currency));
        self::assertSame($expectedBelow, $validator->isBelowMaximum($amount, $currency));
    }

    public function boundaryConditionsDataProvider(): array
    {
        return [
            'no limits' => [null, null, 50.0, true, true],
            'only minimum limit' => [10.0, null, 50.0, true, true],
            'only maximum limit' => [null, 100.0, 50.0, true, true],
            'amount at minimum' => [10.0, 100.0, 10.0, true, true],
            'amount at maximum' => [10.0, 100.0, 100.0, true, true],
            'amount below minimum' => [10.0, 100.0, 9.99, false, true],
            'amount above maximum' => [10.0, 100.0, 100.01, true, false],
            'amount between limits' => [10.0, 100.0, 50.0, true, true],
        ];
    }
}
