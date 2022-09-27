<?php

namespace Oro\Bundle\StripeBundle\Tests\Behat\Mock\Client;

use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Stripe\Collection;
use Stripe\PaymentIntent;

class StripeGatewayMock implements StripeGatewayInterface
{
    private const NO_AUTH_CARD = '4242 4242 4242 4242';
    private const AUTH_CARD = '4000 0027 6000 3184';
    private const ERROR_CARD = '4000 0000 0000 9235';

    private StripePaymentConfig $config;

    public function __construct(StripePaymentConfig $config)
    {
        $this->config = $config;
    }

    public function purchase(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $data = $request->getRequestData();
        $paymentIntent->status = $this->getStatus($data['payment_method']);

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    public function confirm(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $paymentIntent->status = $this->getStatus($request->getPaymentId());

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    public function capture(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $paymentIntent->status = 'succeeded';

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    public function createCustomer(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        // TODO: Implement createCustomer() method.
    }

    public function createSetupIntent(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        // TODO: Implement createSetupIntent() method.
    }

    public function findSetupIntentCustomer(string $setupIntentId): ResponseObjectInterface
    {
        // TODO: Implement findSetupIntentCustomer() method.
    }

    public function findSetupIntent(string $setupIntentId): ResponseObjectInterface
    {
        // TODO: Implement findSetupIntent() method.
    }


    private function createPaymentIntent(): PaymentIntent
    {
        $chargesCollection = new Collection();
        $chargesCollection->offsetSet('balance_transaction', 'test');

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'charges' => $chargesCollection
        ]);

        return $paymentIntent;
    }

    private function getStatus(string $card): string
    {
        return match ($card) {
            self::NO_AUTH_CARD => 'succeeded',
            self::AUTH_CARD => 'requires_action',
            self::ERROR_CARD => 'error',
        };
    }
}
