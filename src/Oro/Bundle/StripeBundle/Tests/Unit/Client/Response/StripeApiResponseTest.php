<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Response;

use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class StripeApiResponseTest extends TestCase
{
    public function testApiResponseSuccessfulTrue()
    {
        $responseData = [
            'id' => 'pi_5',
            'amount' => 700,
            'amount_capturable' => 700,
            'amount_received' => 700,
            'status' => 'succeeded',
            'next_action' => null,
        ];

        $result = $this->createApeResponse($responseData);

        $this->assertTrue($result->isSuccessful());
        $this->assertEquals([
            'successful' => true
        ], $result->prepareResponse());
    }

    public function testApiResponseSuccessfulFalse()
    {
        $responseData = [
            'id' => 'pi_5',
            'amount' => 700,
            'amount_capturable' => 700,
            'amount_received' => 700,
            'status' => 'requires_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
        ];

        $result = $this->createApeResponse($responseData);

        $this->assertFalse($result->isSuccessful());
        $this->assertEquals([
            'successful' => false
        ], $result->prepareResponse());
    }

    private function createApeResponse($responseData): StripeApiResponse
    {
        $responseObject = new PaymentIntentResponse($responseData);
        return new StripeApiResponse($responseObject);
    }
}
