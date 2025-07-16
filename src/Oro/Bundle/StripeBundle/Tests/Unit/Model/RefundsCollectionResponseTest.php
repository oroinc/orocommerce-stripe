<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Model;

use Oro\Bundle\StripeBundle\Model\RefundResponse;
use Oro\Bundle\StripeBundle\Model\RefundsCollectionResponse;
use PHPUnit\Framework\TestCase;

class RefundsCollectionResponseTest extends TestCase
{
    public function testCollectionIterable(): void
    {
        $responseData = [
            'data' => [
                [
                    'id' => 're_1',
                    'object' => 'refund',
                    'amount' => 100,
                    'balance_transaction' => null,
                    'charge' => 'ch_1',
                    'created' => 1664898946,
                    'currency' => 'usd',
                    'metadata' => [],
                    'payment_intent' => 'pi_1',
                    'reason' => null,
                    'receipt_number' => null,
                    'source_transfer_reversal' => null,
                    'status' => 'succeeded',
                    'transfer_reversal' => null
                ],
                [
                    'id' => 're_2',
                    'object' => 'refund',
                    'amount' => 50,
                    'balance_transaction' => 'bt_2',
                    'charge' => 'ch_1',
                    'created' => 1664898946,
                    'currency' => 'usd',
                    'metadata' => [],
                    'payment_intent' => 'pi_1',
                    'reason' => null,
                    'receipt_number' => null,
                    'source_transfer_reversal' => null,
                    'status' => 'succeeded',
                    'transfer_reversal' => null
                ]
            ]
        ];

        $response = new RefundsCollectionResponse($responseData);
        $firstItem = $response->getIterator()->current();

        $this->assertIsIterable($response);
        $this->assertCount(2, $response->getIterator());
        $this->assertInstanceOf(RefundResponse::class, $firstItem);
    }
}
