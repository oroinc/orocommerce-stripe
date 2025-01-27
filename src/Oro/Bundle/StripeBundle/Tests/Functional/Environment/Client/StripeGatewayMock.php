<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\Environment\Client;

use Oro\Bundle\StripeBundle\Client\Request\StripeApiRequestInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
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
    public const NO_AUTH_CARD = '4242 4242 4242 4242';
    public const AUTH_CARD = '4000 0027 6000 3184';
    public const ERROR_CARD = '4000 0000 0000 9235';

    #[\Override]
    public function purchase(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = $this->createPaymentIntent();
        $data = $request->getRequestData();
        $paymentIntent->status = $this->getStatus($data['payment_method']);
        if (PaymentIntent::STATUS_REQUIRES_ACTION === $paymentIntent->status) {
            $paymentIntent->next_action = ['type' => 'use_stripe_sdk'];
        }

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
        $paymentIntent->status = PaymentIntent::STATUS_SUCCEEDED;

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    #[\Override]
    public function createCustomer(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        return new CustomerResponse($this->createCustomerObject()->toArray());
    }

    #[\Override]
    public function createSetupIntent(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        return new SetupIntentResponse($this->createSetupIntentObject()->toArray());
    }

    #[\Override]
    public function findSetupIntentCustomer(string $setupIntentId): ResponseObjectInterface
    {
        return new CustomerResponse($this->createCustomerObject()->toArray());
    }

    #[\Override]
    public function findSetupIntent(string $setupIntentId): ResponseObjectInterface
    {
        return new SetupIntentResponse($this->createSetupIntentObject()->toArray());
    }

    #[\Override]
    public function cancel(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => PaymentIntent::STATUS_CANCELED,
            'charges' => []
        ]);

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    #[\Override]
    public function refund(StripeApiRequestInterface $request): ResponseObjectInterface
    {
        $refund = Refund::constructFrom([
            'id' => 'ref_1',
            'payment_intent' => 'pi_1',
            'status' => PaymentIntent::STATUS_SUCCEEDED
        ]);

        return new RefundResponse($refund->toArray());
    }

    #[\Override]
    public function getAllRefunds(array $criteria): CollectionResponseInterface
    {
        $refund1 = Refund::constructFrom([
            'id' => 're_1',
            'payment_intent' => 'pi_1',
            'status' => PaymentIntent::STATUS_SUCCEEDED
        ]);
        $refund2 = Refund::constructFrom([
            'id' => 're_2',
            'payment_intent' => 'pi_1',
            'status' => PaymentIntent::STATUS_SUCCEEDED
        ]);

        return new RefundsCollectionResponse((new Collection(['data' => [$refund1, $refund2]]))->toArray());
    }

    private function createPaymentIntent(): PaymentIntent
    {
        $chargesCollection = new Collection();
        $chargesCollection->offsetSet('balance_transaction', 'test');

        return PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'charges' => $chargesCollection
        ]);
    }

    private function createCustomerObject(): Customer
    {
        return Customer::constructFrom(['id' => 'cus_1']);
    }

    private function createSetupIntentObject(): SetupIntent
    {
        return SetupIntent::constructFrom([
            'id' => 'seti_1',
            'payment_method' => 'pm_1',
            'status' => PaymentIntent::STATUS_SUCCEEDED
        ]);
    }

    private function getStatus(string $card): string
    {
        return match ($card) {
            self::NO_AUTH_CARD => PaymentIntent::STATUS_SUCCEEDED,
            self::AUTH_CARD => PaymentIntent::STATUS_REQUIRES_ACTION,
            self::ERROR_CARD => 'error'
        };
    }
}
