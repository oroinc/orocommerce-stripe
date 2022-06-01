<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Model;

use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use PHPUnit\Framework\TestCase;

class ChargeResponseTest extends TestCase
{
    public function testChargeResponseObject()
    {
        $data = $this->getResponseTestData();
        $chargeResponse = new ChargeResponse($data);

        $this->assertEquals('pi_1', $chargeResponse->getPaymentIntentId());
        $this->assertEquals('succeeded', $chargeResponse->getStatus());
        $this->assertEquals('ch_1', $chargeResponse->getIdentifier());
        $this->assertEquals(50, $chargeResponse->getLastRefundedAmount());
        $this->assertEquals(1, $chargeResponse->getRefundsCount());

        $responseData = $chargeResponse->getData();

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals([
            'id' => 'ch_1',
            'amount' => 100,
            'amount_captured' => 50,
            'amount_refunded' => 50,
            'balance_transaction' => 'txn_1',
            'billing_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'Test country'
                ]
            ],
            'captured' => true,
            'currency' => 'usd',
            'created' => 1640181498,
            'failure_code' => 'test code',
            'failure_message' => 'failed',
            'fraud_details' => [],
            'order' => 10,
            'payment_intent' => 'pi_1',
            'payment_method' => 'card_1',
            'refunds' => [
                'data' => [
                    [
                        'id' => 'ref_1',
                        'amount' => 50,
                        'status' => 'succeeded'
                    ]
                ],
                'total_count' => 1
            ],
            'status' => 'succeeded'
        ], $responseData['data']);
    }

    public function testChargeResponseObjectWitEmptyRefunds()
    {
        $data = $this->getResponseTestData();
        $data['refunds'] = [
            'data' => [],
            'total_count' => 0
        ];

        $chargeResponse = new ChargeResponse($data);

        $this->assertEquals(0, $chargeResponse->getLastRefundedAmount());
        $this->assertEquals(0, $chargeResponse->getRefundsCount());
    }

    public function testChargeResponseObjectWithoutRefunds()
    {
        $data = $this->getResponseTestData();
        unset($data['refunds']);

        $chargeResponse = new ChargeResponse($data);

        $this->assertEquals(0, $chargeResponse->getLastRefundedAmount());
        $this->assertEquals(0, $chargeResponse->getRefundsCount());
    }

    private function getResponseTestData()
    {
        return [
            'id' => 'ch_1',
            'object' => 'charge',
            'amount' => 100,
            'amount_captured' => 50,
            'amount_refunded' => 50,
            'balance_transaction' => 'txn_1',
            'billing_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'Test country'
                ]
            ],
            'calculated_statement_descriptor' => null,
            'captured' => true,
            'currency' => 'usd',
            'created' => 1640181498,
            'failure_code' => 'test code',
            'failure_message' => 'failed',
            'fraud_details' => [],
            'order' => 10,
            'payment_intent' => 'pi_1',
            'payment_method' => 'card_1',
            'refunds' => [
                'data' => [
                    [
                        'id' => 'ref_1',
                        'amount' => 50,
                        'status' => 'succeeded'
                    ]
                ],
                'total_count' => 1
            ],
            'status' => 'succeeded'
        ];
    }
}
