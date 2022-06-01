<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Response;

use Oro\Bundle\StripeBundle\Client\Response\PurchaseResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class PurchaseResponseTest extends TestCase
{
    public function testIsSuccessfulSuccess(): void
    {
        $responseData = [
            'id' => 5,
            'amount' => 700,
            'amount_capturable' => 700,
            'amount_received' => 700,
            'status' => 'succeeded',
            'next_action' => null,
        ];

        $responseObject = new PaymentIntentResponse($responseData);
        $result = new PurchaseResponse($responseObject);
        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulFailed(): void
    {
        $responseData = [
            'id' => 5,
            'amount' => 700,
            'amount_capturable' => 700,
            'amount_received' => 700,
            'status' => 'requires_action',
            'next_action' => null,
        ];

        $responseObject = new PaymentIntentResponse($responseData);
        $result = new PurchaseResponse($responseObject);
        $this->assertFalse($result->isSuccessful());
    }

    public function testPrepareResponse(): void
    {
        $expected = [
            'successful' => true,
            'requires_action' => false,
            'payment_intent_client_secret' => null
        ];

        $responseData = [
            'id' => 5,
            'amount' => 700,
            'amount_capturable' => 700,
            'amount_received' => 700,
            'status' => 'succeeded',
            'next_action' => null,
        ];

        $responseObject = new PaymentIntentResponse($responseData);
        $result = new PurchaseResponse($responseObject);
        $this->assertEquals($expected, $result->prepareResponse());
    }

    public function testPrepareResponseWithAdditionAction(): void
    {
        $responseData = [
            'id' => 5,
            'amount' => 700,
            'amount_capturable' => 700,
            'amount_received' => 700,
            'status' => 'requires_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
        ];

        $expected = [
            'successful' => false,
            'requires_action' => true,
            'payment_intent_client_secret' => null
        ];

        $responseObject = new PaymentIntentResponse($responseData);
        $result = new PurchaseResponse($responseObject);
        $this->assertEquals($expected, $result->prepareResponse());
    }
}
