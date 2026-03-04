<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\Environment\Client;

use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClient;
use Stripe\Customer;
use Stripe\PaymentIntent;

final class StripePaymentElementTestData
{
    public const string NO_AUTH_TOKEN = 'ctoken_visa_success';
    public const string AUTH_TOKEN = 'ctoken_visa_3ds';
    public const string ERROR_TOKEN = 'ctoken_visa_declined';

    public static function mockSuccessfulPayment(): void
    {
        MockingStripeClient::addMockResponse(self::createPaymentIntent([
            'id' => 'pi_test_success_' . uniqid(),
            'status' => 'succeeded',
            'charges' => [
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'ch_test_' . uniqid(),
                        'object' => 'charge',
                        'status' => 'succeeded',
                        'paid' => true,
                    ],
                ],
            ],
        ]));
    }

    public static function mockDeclinedPayment(): void
    {
        MockingStripeClient::addMockResponse(self::createPaymentIntent([
            'id' => 'pi_test_declined_' . uniqid(),
            'status' => 'requires_payment_method',
            'last_payment_error' => [
                'code' => 'card_declined',
                'decline_code' => 'generic_decline',
                'message' => 'Your card was declined',
            ],
        ]));
    }

    public static function mockFindCustomer(): void
    {
        MockingStripeClient::addMockResponse(self::createCustomer());
    }

    public static function mockPaymentRequiresAction(): void
    {
        MockingStripeClient::addMockResponse(self::createPaymentIntent([
            'id' => 'pi_test_requires_action_' . uniqid(),
            'status' => 'requires_action',
            'next_action' => [
                'type' => 'use_stripe_sdk',
            ],
        ]));
    }

    private static function createPaymentIntent(array $data): PaymentIntent
    {
        return PaymentIntent::constructFrom(array_merge([
            'object' => 'payment_intent',
            'amount' => 10000,
            'currency' => 'usd',
            'client_secret' => 'pi_test_secret',
            'payment_method' => 'pm_card_visa',
        ], $data));
    }

    private static function createCustomer(array $data = []): Customer
    {
        return Customer::constructFrom(array_merge(['id' => 'cus_1'], $data));
    }
}
