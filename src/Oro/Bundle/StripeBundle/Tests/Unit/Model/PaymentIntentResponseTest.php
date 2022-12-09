<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Model;

use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class PaymentIntentResponseTest extends TestCase
{
    public function testPaymentIntentResponseObject()
    {
        $data = $this->getResponseTestData();
        $paymentIntentResponse = new PaymentIntentResponse($data);

        $this->assertEquals('succeeded', $paymentIntentResponse->getStatus());
        $this->assertEquals('pi_1', $paymentIntentResponse->getIdentifier());
        $this->assertNull($paymentIntentResponse->getNextActionType());
        $this->assertEquals('secret', $paymentIntentResponse->getClientSecret());

        $responseData = $paymentIntentResponse->getData();

        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('paymentIntentId', $responseData);

        $this->assertEquals([
            'paymentIntentId' => 'pi_1',
            'data' => [
                'amount' => 200,
                'amount_capturable' => 200,
                'amount_received' => 100,
                'canceled_at' => null,
                'cancellation_reason' => null,
                'capture_method' => 'manual',
                'charges' => [
                    [
                        'id' => 'ch_1',
                        'amount' => 100,
                        'amount_captured' => 100,
                        'amount_refunded' => 0,
                        'balance_transaction' => null,
                        'currency' => 'usd',
                        'refunds' => [],
                        'status' => 'succeeded'
                    ]
                ],
                'confirmation_method' => 'manual',
                'created' => 1640272165,
                'currency' => 'usd',
                'customer' => null,
                'invoice' => null,
                'last_payment_error' => null,
                'livemode' => false,
                'metadata' => [
                    'order_id' => 1
                ],
                'next_action' => null,
                'payment_method' => null,
                'processing' => null,
                'status' => 'succeeded'
            ]
        ], $responseData);
    }

    public function testNextActionTypeNotNull()
    {
        $data = $this->getResponseTestData();
        $data['next_action'] = [
            'type' => 'use_stripe_sdk'
        ];

        $paymentIntentResponse = new PaymentIntentResponse($data);

        $this->assertEquals('use_stripe_sdk', $paymentIntentResponse->getNextActionType());
    }

    private function getResponseTestData()
    {
        return [
            'id' => 'pi_1',
            'amount' => 200,
            'amount_capturable' => 200,
            'amount_received' => 100,
            'canceled_at' => null,
            'cancellation_reason' => null,
            'capture_method' => 'manual',
            'charges' => [
                'data' => [
                    [
                        'id' => 'ch_1',
                        'amount' => 100,
                        'amount_captured' => 100,
                        'amount_refunded' => 0,
                        'balance_transaction' => null,
                        'calculated_statement_descriptor' => 'Stripe',
                        'captured' => false,
                        'currency' => 'usd',
                        'refunds' => [],
                        'status' => 'succeeded'
                    ]
                ]
            ],
            'client_secret' => 'secret',
            'confirmation_method' => 'manual',
            'created' => 1640272165,
            'currency' => 'usd',
            'customer' => null,
            'description' => 'Test description',
            'on_behalf_of' => null,
            'invoice' => null,
            'last_payment_error' => null,
            'livemode' => false,
            'metadata' => [
                'order_id' => 1
            ],
            'next_action' => null,
            'payment_method' => null,
            'processing' => null,
            'status' => 'succeeded',
            'transfer_data' => null,
            'transfer_group' => null
        ];
    }
}
