<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Model;

use Oro\Bundle\StripeBundle\Model\RefundResponse;
use PHPUnit\Framework\TestCase;

class RefundResponseTest extends TestCase
{
    /**
     * @dataProvider responseDataProvider
     */
    public function testResponseObject(
        array $data,
        array $expected,
        ?string $paymentIntentId,
        string $status,
        string $identifier
    ) {
        $response = new RefundResponse($data);

        $this->assertEquals($paymentIntentId, $response->getPaymentIntentId());
        $this->assertEquals($status, $response->getStatus());
        $this->assertEquals($identifier, $response->getIdentifier());

        $responseData = $response->getData();

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($expected, $responseData['data']);
    }

    public function responseDataProvider(): \Generator
    {
        yield [
            [
                'id' => 're_3LpDTCFjQYPlr3hE1Amggqup',
                'object' => 'refund',
                'amount' => 100,
                'balance_transaction' => null,
                'charge' => 'ch_3LpDTCFjQYPlr3hE1mfI0etx',
                'created' => 1664898946,
                'currency' => 'usd',
                'metadata' => [],
                'payment_intent' => null,
                'reason' => null,
                'receipt_number' => null,
                'source_transfer_reversal' => null,
                'status' => 'succeeded',
                'transfer_reversal' => null
            ],
            [
                'id' => 're_3LpDTCFjQYPlr3hE1Amggqup',
                'amount' => 100,
                'balance_transaction' => null,
                'currency' => 'usd',
                'payment_intent' => null,
                'status' => 'succeeded',
                'metadata' => [],
                'reason' => null
            ],
            null,
            'succeeded',
            're_3LpDTCFjQYPlr3hE1Amggqup'
        ];

        yield [
            [
                'id' => 're_3LpDTCFjQYPlr3hE1Amggqup',
                'object' => 'refund',
                'amount' => 100,
                'balance_transaction' => null,
                'charge' => 'ch_3LpDTCFjQYPlr3hE1mfI0etx',
                'created' => 1664898946,
                'currency' => 'usd',
                'metadata' => [],
                'payment_intent' => 'pi1',
                'reason' => null,
                'receipt_number' => null,
                'source_transfer_reversal' => null,
                'status' => 'succeeded',
                'transfer_reversal' => null
            ],
            [
                'id' => 're_3LpDTCFjQYPlr3hE1Amggqup',
                'amount' => 100,
                'balance_transaction' => null,
                'currency' => 'usd',
                'payment_intent' => 'pi1',
                'status' => 'succeeded',
                'metadata' => [],
                'reason' => null
            ],
            'pi1',
            'succeeded',
            're_3LpDTCFjQYPlr3hE1Amggqup'
        ];
    }
}
