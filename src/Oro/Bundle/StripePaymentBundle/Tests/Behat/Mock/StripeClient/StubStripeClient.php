<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Behat\Mock\StripeClient;

use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfiguration;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Stripe\Customer as StripeCustomer;
use Stripe\Exception\InvalidRequestException;
use Stripe\PaymentIntent as StripePaymentIntent;
use Stripe\Refund as StripeRefund;
use Stripe\SearchResult as StripeSearchResult;
use Stripe\StripeObject;

/**
 * A Stripe client stub suitable for using in behat tests.
 */
class StubStripeClient extends LoggingStripeClient
{
    public function __construct()
    {
        parent::__construct(['api_key' => 'sk_test_stub']);
    }

    /**
     * @throws InvalidRequestException
     */
    #[\Override]
    protected function doRequest($method, $path, $params, $opts): StripeObject
    {
        if ($path === '/v1/customers/search') {
            $searchResult = new StripeSearchResult();
            $searchResult->data = [new StripeCustomer('cus_123')];

            return $searchResult;
        }

        if ($path === '/v1/payment_intents') {
            $cardNumber = str_replace(['tok_', ' '], '', $params['confirmation_token'] ?? '');

            $paymentIntent = new StripePaymentIntent('pi_123');
            $paymentIntent->customer = 'cus_123';
            $paymentIntent->payment_method = 'pm_123';

            match ($cardNumber) {
                '4242424242424242' => $paymentIntent->status = 'succeeded',
                '4000002760003184' => $paymentIntent->status = 'requires_action',
                '4000000000009235' => $paymentIntent->status = 'failed',
                default => $paymentIntent->status = 'unknown',
            };

            return $paymentIntent;
        }

        if ($path === '/v1/refunds') {
            $paymentIntentId = $params['payment_intent'] ?? null;

            $refund = new StripeRefund('pi_123');
            $refund->status = 'succeeded';
            $refund->amount = $params['amount'];
            $refund->currency = $params['currency'] ?? CurrencyConfiguration::DEFAULT_CURRENCY;
            $refund->payment_intent = $paymentIntentId;

            return $refund;
        }

        throw InvalidRequestException::factory('Request to "' . $path . '" is not supported by ' . __CLASS__);
    }
}
