<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Response;

use Oro\Bundle\StripeBundle\Client\Response\SetupIntentResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse as SetupIntentResponseObject;
use PHPUnit\Framework\TestCase;

class SetupIntentResponseTest extends TestCase
{
    public function testIsSuccessfulSuccess(): void
    {
        $responseData = [
            'id' => 'seti_100',
            'object' =>  'setup_intent',
            'status' => 'succeeded',
            'next_action' => null,
        ];

        $responseObject = new SetupIntentResponseObject($responseData);
        $result = new SetupIntentResponse($responseObject);
        $this->assertTrue($result->isSuccessful());
    }

    public function testIsSuccessfulFailed(): void
    {
        $responseData = [
            'id' => 'seti_100',
            'object' =>  'setup_intent',
            'status' => 'requires_action',
            'next_action' => null,
        ];

        $responseObject = new SetupIntentResponseObject($responseData);
        $result = new SetupIntentResponse($responseObject);
        $this->assertFalse($result->isSuccessful());
    }

    public function testPrepareResponse(): void
    {
        $expected = [
            'successful' => true,
            'requires_action' => false,
            'setup_intent_client_secret' => null
        ];

        $responseData = [
            'id' => 'seti_100',
            'object' =>  'setup_intent',
            'status' => 'succeeded',
            'next_action' => null,
        ];

        $responseObject = new SetupIntentResponseObject($responseData);
        $result = new SetupIntentResponse($responseObject);
        $this->assertEquals($expected, $result->prepareResponse());
    }

    public function testPrepareResponseWithAdditionAction(): void
    {
        $responseData = [
            'id' => 'seti_100',
            'object' =>  'setup_intent',
            'status' => 'requires_action',
            'next_action' => [
                'type' => 'use_stripe_sdk'
            ],
        ];

        $expected = [
            'successful' => false,
            'requires_action' => true,
            'setup_intent_client_secret' => null
        ];

        $responseObject = new SetupIntentResponseObject($responseData);
        $result = new SetupIntentResponse($responseObject);
        $this->assertEquals($expected, $result->prepareResponse());
    }
}
