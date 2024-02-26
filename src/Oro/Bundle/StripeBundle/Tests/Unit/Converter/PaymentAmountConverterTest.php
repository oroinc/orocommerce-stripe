<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Converter;

use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;
use PHPUnit\Framework\TestCase;
use TypeError;

class PaymentAmountConverterTest extends TestCase
{
    /**
     * @dataProvider converterData
     */
    public function testConvertToStripeFormat(array $currencies, mixed $amount, int $expected): void
    {
        foreach ($currencies as $currency) {
            $this->assertEquals($expected, PaymentAmountConverter::convertToStripeFormatUsingCurrency(
                $amount,
                $currency
            ));
        }
    }

    /**
     * @dataProvider reverseConverterData
     */
    public function testConvertFromStripeFormat(array $currencies, mixed $amount, int|float $expected): void
    {
        foreach ($currencies as $currency) {
            $this->assertEquals($expected, PaymentAmountConverter::convertFromStripeFormatUsingCurrency(
                $amount,
                $currency
            ));
        }
    }

    public function testConvertToStripeFormatWithNull(): void
    {
        $this->expectException(TypeError::class);
        PaymentAmountConverter::convertToStripeFormatUsingCurrency(null, 'USD');
    }

    public function testConvertToStripeFormatWithString(): void
    {
        $this->expectException(TypeError::class);
        PaymentAmountConverter::convertToStripeFormatUsingCurrency('');
    }

    public function converterData(): array
    {
        return [
            [
                'currencies' => ['USD', 'EUR'],
                'amount' => 0.0,
                'expected' => 0,
            ],
            [
                'currencies' => ['ZAR', 'DZD'],
                'amount' => 7.66,
                'expected' => 766,
            ],
            [
                'currencies' => ['TWD', 'RON'],
                'amount' => '3.44',
                'expected' => 344,
            ],
            [
                'currencies' => ['BSD', 'CHF'],
                'amount' => 15.99 * 2,
                'expected' => 3198
            ],
            # Zero-decimal currencies
            [
                'currencies' => ['BIF', 'CLP', 'DJF','GNF'],
                'amount' => 0.0,
                'expected' => 0,
            ],
            [
                'currencies' => ['JPY', 'KMF', 'KRW', 'MGA'],
                'amount' => 7.66,
                'expected' => 8,
            ],
            [
                'currencies' => ['PYG', 'RWF', 'UGX', 'VND'],
                'amount' => '3.44',
                'expected' => 3,
            ],
            [
                'currencies' => ['VUV', 'XAF', 'XOF', 'XPF'],
                'amount' => 15.99 * 2,
                'expected' => 32
            ],
            # Three-decimal currencies
            [
                'currencies' => ['BHD'],
                'amount' => 15.1250,
                'expected' => 15130,
            ],
            [
                'currencies' => ['BHD'],
                'amount' => 15.1249,
                'expected' => 15120,
            ],
            [
                'currencies' => ['JOD', 'KWD'],
                'amount' => 0.124,
                'expected' => 120,
            ],
            [
                'currencies' => ['OMR'],
                'amount' => '3.44',
                'expected' => 3440,
            ],
            [
                'currencies' => ['TND'],
                'amount' => 15.99 * 2,
                'expected' => 31980
            ]
        ];
    }

    public function reverseConverterData(): array
    {
        return [
            [
                'currencies' => ['USD', 'EUR'],
                'amount' => 0,
                'expected' => 0,
            ],
            [
                'currencies' => ['ZAR', 'DZD'],
                'amount' => 766,
                'expected' => 7.66,
            ],
            # Zero-decimal currencies
            [
                'currencies' => ['BIF', 'CLP', 'DJF','GNF'],
                'amount' => 0,
                'expected' => 0,
            ],
            [
                'currencies' => ['JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'],
                'amount' => 10,
                'expected' => 10,
            ],
            # Three-decimal currencies
            [
                'currencies' => ['BHD'],
                'amount' => 15120,
                'expected' => 15.120,
            ],
            [
                'currencies' => ['JOD', 'KWD'],
                'amount' => 120,
                'expected' => 0.120,
            ],
            [
                'currencies' => ['OMR'],
                'amount' => 11200,
                'expected' => 11.2, // 11.200
            ],
            [
                'currencies' => ['TND'],
                'amount' => 0001,
                'expected' => 0.001
            ]
        ];
    }
}
