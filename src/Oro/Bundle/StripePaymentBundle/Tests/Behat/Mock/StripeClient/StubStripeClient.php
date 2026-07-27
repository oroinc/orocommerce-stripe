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
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
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
            $paymentIntent->payment_method = 'pm_' . $cardNumber;

            if ($cardNumber === '4242424242424242') { // Successful charge
                $paymentIntent->status = 'succeeded';
            } elseif ($cardNumber === '4000056655665556') { // Successful authorization, requires capture
                $paymentIntent->status = 'requires_capture';
                if (isset($params['setup_future_usage'])) {
                    $paymentIntent->setup_future_usage = $params['setup_future_usage'];
                }
            } elseif ($cardNumber === '4000000000009235') { // Declined card
                $paymentIntent->status = 'requires_payment_method';
                $paymentIntent->last_payment_error = StripeObject::constructFrom([
                    'message' => 'Your card was declined.',
                    'code' => 'card_declined',
                    'decline_code' => 'insufficient_funds',
                ]);
            } elseif (!empty($params['payment_method']) && !empty($params['off_session'])) {
                $paymentIntent->payment_method = $params['payment_method'];
                if ($params['payment_method'] === 'pm_test_failing_card_visa') {  // Re-authorization
                    $paymentIntent->status = 'requires_payment_method';
                    $paymentIntent->last_payment_error = StripeObject::constructFrom([
                        'message' => 'Your card was declined.',
                        'code' => 'card_declined',
                        'decline_code' => 'insufficient_funds',
                    ]);
                } elseif ($params['payment_method'] === 'pm_test_card_visa') {  // Re-authorization
                    $paymentIntent->status = 'requires_capture';
                } elseif ($params['payment_method'] === 'pm_4000056655665556') {  // Authorization
                    $paymentIntent->status = 'requires_capture';
                } else {
                    $paymentIntent->status = 'succeeded';
                }
            }

            return $paymentIntent;
        }

        if (str_starts_with($path, '/v1/payment_intents/') && str_ends_with($path, '/capture')) {
            $paymentIntent = new StripePaymentIntent('pi_123');
            $paymentIntent->status = 'succeeded';
            $paymentIntent->amount_received = $params['amount_to_capture'] ?? 0;

            return $paymentIntent;
        }

        if (str_starts_with($path, '/v1/payment_intents/') && str_ends_with($path, '/cancel')) {
            $paymentIntent = new StripePaymentIntent('pi_123');
            $paymentIntent->status = 'canceled';

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
