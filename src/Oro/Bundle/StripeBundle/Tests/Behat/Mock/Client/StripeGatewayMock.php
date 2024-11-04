<?php

namespace Oro\Bundle\StripeBundle\Tests\Behat\Mock\Client;

use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\CollectionResponseInterface;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\RefundResponse;
use Oro\Bundle\StripeBundle\Model\RefundsCollectionResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\SetupIntent;

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

    #[\Override]
    public function purchase(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $data = $request->getRequestData();
        $paymentIntent->status = $this->getStatus($data['payment_method']);

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    #[\Override]
    public function confirm(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $paymentIntent->status = $this->getStatus($request->getPaymentId());

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    #[\Override]
    public function capture(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $paymentIntent->status = 'succeeded';

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    #[\Override]
    public function createCustomer(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $customer = $this->createCustomerObject();
        return new CustomerResponse($customer->toArray());
    }

    #[\Override]
    public function createSetupIntent(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $setupIntent = $this->createSetupIntentObject();
        return new SetupIntentResponse($setupIntent->toArray());
    }

    #[\Override]
    public function findSetupIntentCustomer(string $setupIntentId): ResponseObjectInterface
    {
        $customer = $this->createCustomerObject();
        return new CustomerResponse($customer->toArray());
    }

    #[\Override]
    public function findSetupIntent(string $setupIntentId): ResponseObjectInterface
    {
        $setupIntent = $this->createSetupIntentObject();
        return new SetupIntentResponse($setupIntent->toArray());
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

    private function createCustomerObject()
    {
        return $paymentIntent = Customer::constructFrom([
            'id' => 'cus_1',
        ]);
    }

    private function createSetupIntentObject()
    {
        return SetupIntent::constructFrom([
            'id' => 'seti_1',
            'payment_method' => 'pm_1',
            'status' => 'succeeded'
        ]);
    }

    private function getStatus(string $card): string
    {
        return match ($card) {
            self::NO_AUTH_CARD => 'succeeded',
            self::AUTH_CARD => 'requires_action',
            self::ERROR_CARD => 'error',
        };
    }

    #[\Override]
    public function cancel(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'canceled',
            'charges' => [],
        ]);

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    #[\Override]
    public function refund(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $refund = Refund::constructFrom([
            'id' => 'ref_1',
            'payment_intent' => 'pi_1',
            'status' => 'succeeded'
        ]);

        return new RefundResponse($refund->toArray());
    }

    #[\Override]
    public function getAllRefunds(array $criteria): CollectionResponseInterface
    {
        $refund1 = Refund::constructFrom([
            'id' => 're_1',
            'payment_intent' => 'pi_1',
            'status' => 'succeeded'
        ]);

        $refund2 = Refund::constructFrom([
            'id' => 're_2',
            'payment_intent' => 'pi_1',
            'status' => 'succeeded'
        ]);

        $refundsCollection = new Collection([
            'data' => [
                $refund1,
                $refund2
            ]
        ]);

        return new RefundsCollectionResponse($refundsCollection->toArray());
    }
}
