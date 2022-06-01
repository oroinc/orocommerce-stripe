<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Converter;

use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;
use PHPUnit\Framework\TestCase;
use TypeError;

class PaymentAmountConverterTest extends TestCase
{
    /**
     * @dataProvider converterData
     * @param mixed $amount
     * @param mixed $expected
     */
    public function testConvertToStripeFormat($amount, $expected): void
    {
        $this->assertEquals($expected, PaymentAmountConverter::convertToStripeFormat($amount));
    }

    public function testConvertToStripeFormatWithNull(): void
    {
        $this->expectException(TypeError::class);
        $result = PaymentAmountConverter::convertToStripeFormat(null);
    }

    public function testConvertToStripeFormatWithString(): void
    {
        $this->expectException(TypeError::class);
        $result = PaymentAmountConverter::convertToStripeFormat('');
    }

    public function converterData(): array
    {
        return [
            [
                'amount' => 0.0,
                'expected' => 0,
            ],
            [
                'amount' => 7.66,
                'expected' => 766,
            ],
            [
                'amount' => '3.44',
                'expected' => 344,
            ],
            [
                'amount' => 15.99 * 2,
                'expected' => 3198
            ]
        ];
    }
}
