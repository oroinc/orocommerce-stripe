<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Model;

use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use PHPUnit\Framework\TestCase;

class SetupIntentResponseTest extends TestCase
{
    /**
     * @dataProvider responseDataProvider
     */
    public function testResponseObject(
        array $data,
        array $expected,
        ?string $nextActionType,
        string $status,
        string $identifier,
        string $clientSecret
    ) {
        $response = new SetupIntentResponse($data);

        $this->assertEquals($nextActionType, $response->getNextActionType());
        $this->assertEquals($status, $response->getStatus());
        $this->assertEquals($identifier, $response->getIdentifier());
        $this->assertEquals($clientSecret, $response->getClientSecret());

        $responseData = $response->getData();

        $this->assertArrayHasKey('data', $responseData);
        $this->assertEquals($expected, $responseData['data']);
    }

    /**
     * @return \Generator
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function responseDataProvider(): \Generator
    {
        yield [
            [
                'id' => 'seti_1LhuWHFjQYPlr3hEMXe2rBgn',
                'object' => 'setup_intent',
                'application' => null,
                'cancellation_reason' => null,
                'client_secret' => 'secret',
                'created' => 1663157805,
                'customer' => 'cus_MQlqr36iGByka9',
                'description' => null,
                'flow_directions' => null,
                'last_setup_error' => [
                    'code' => 'setup_intent_authentication_failure',
                    'doc_url' => 'https://stripe.com/docs/error-codes/setup-intent-authentication-failure',
                    'message' => 'The latest attempt failed.',
                    'payment_method' => [
                        'id' => 'pm_1Lhtw7FjQYPlr3hE4d3S7cYU',
                        'object' => 'payment_method',
                        'billing_details' => [
                            'address' => [
                                'city' => null,
                                'country' => null,
                                'line1' => null,
                                'line2' => null,
                                'postal_code' => '90000',
                                'state' => null
                            ],
                            'email' => null,
                            'name' => null,
                            'phone' => null
                        ],
                        'card' => [
                            'brand' => 'visa',
                            'checks' => [
                                'address_line1_check' => null,
                                'address_postal_code_check' => 'pass',
                                'cvc_check' => 'pass'
                            ],
                            'country' => 'FR',
                            'exp_month' => 4,
                            'exp_year' => 2025,
                            'fingerprint' => '5C1JNFnY2kAkUqSD',
                            'funding' => 'credit',
                            'generated_from' => null,
                            'last4' => '3155',
                            'networks' => [
                                'available' => [
                                    'visa'
                                ],
                                'preferred' => null
                            ],
                            'three_d_secure_usage' => [
                                'supported' => true
                            ],
                            'wallet' => null
                        ],
                        'created' => 1663155563,
                        'customer' => 'cus_MQlqr36iGByka9',
                        'livemode' => false,
                        'metadata' => [],
                        'type' => 'card'
                    ],
                    'type' => 'invalid_request_error'
                ],
                'latest_attempt' => 'setatt_1LhuWHFjQYPlr3hEbAAsJT5x',
                'livemode' => false,
                'mandate' => null,
                'metadata' => [
                    'order_id' => '229'
                ],
                'next_action' => null,
                'on_behalf_of' => null,
                'payment_method' => null,
                'payment_method_options' => [
                    'card' => [
                        'mandate_options' => null,
                        'network' => null,
                        'request_three_d_secure' => 'automatic'
                    ]
                ],
                'payment_method_types' => [
                    'card'
                ],
                'single_use_mandate' => null,
                'status' => 'requires_payment_method',
                'usage' => 'off_session'
            ],
            [
                'status' => 'requires_payment_method',
                'metadata' => [
                    'order_id' => '229'
                ],
                'cancellation_reason' => null,
                'created' => 1663157805,
                'customer' => 'cus_MQlqr36iGByka9',
                'description' => null,
                'payment_method' => null,
                'usage' => 'off_session',
                'return_url' => null,
                'livemode' => false,
                'flow_directions' => null,
                'last_setup_error' => [
                    'code' => 'setup_intent_authentication_failure',
                    'doc_url' => 'https://stripe.com/docs/error-codes/setup-intent-authentication-failure',
                    'message' => 'The latest attempt failed.',
                    'payment_method' => [
                        'id' => 'pm_1Lhtw7FjQYPlr3hE4d3S7cYU',
                        'object' => 'payment_method',
                        'billing_details' => [
                            'address' => [
                                'city' => null,
                                'country' => null,
                                'line1' => null,
                                'line2' => null,
                                'postal_code' => '90000',
                                'state' => null
                            ],
                            'email' => null,
                            'name' => null,
                            'phone' => null
                        ],
                        'card' => [
                            'brand' => 'visa',
                            'checks' => [
                                'address_line1_check' => null,
                                'address_postal_code_check' => 'pass',
                                'cvc_check' => 'pass'
                            ],
                            'country' => 'FR',
                            'exp_month' => 4,
                            'exp_year' => 2025,
                            'fingerprint' => '5C1JNFnY2kAkUqSD',
                            'funding' => 'credit',
                            'generated_from' => null,
                            'last4' => '3155',
                            'networks' => [
                                'available' => ['visa'],
                                'preferred' => null
                            ],
                            'three_d_secure_usage' => [
                                'supported' => true
                            ],
                            'wallet' => null
                        ],
                        'created' => 1663155563,
                        'customer' => 'cus_MQlqr36iGByka9',
                        'livemode' => false,
                        'metadata' => [],
                        'type' => 'card'
                    ],
                    'type' => 'invalid_request_error'
                ],
                'latest_attempt' => 'setatt_1LhuWHFjQYPlr3hEbAAsJT5x',
                'mandate' => null,
                'on_behalf_of' => null,
                'single_use_mandate' => null
            ],
            null,
            'requires_payment_method',
            'seti_1LhuWHFjQYPlr3hEMXe2rBgn',
            'secret'
        ];
    }
}
