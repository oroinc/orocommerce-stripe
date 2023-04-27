<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\RefundResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Oro\Bundle\StripeBundle\Model\UnsupportedResponse;
use Stripe\Balance;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\SetupIntent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Create event object based on Stripe SDK API.
 */
class StripeSDKEventFactory implements StripeEventFactoryInterface
{
    private const STRIPE_SIGNATURE = 'HTTP_STRIPE_SIGNATURE';
    private StripePaymentConfigsProvider $paymentConfigsProvider;

    protected array $responseTypeClassMapping = [
        PaymentIntent::class => PaymentIntentResponse::class,
        Balance::class => PaymentIntentResponse::class,
        Charge::class => ChargeResponse::class,
        Customer::class => CustomerResponse::class,
        Refund::class => RefundResponse::class,
        SetupIntent::class => SetupIntentResponse::class,
    ];

    public function __construct(StripePaymentConfigsProvider $paymentConfigsProvider)
    {
        $this->paymentConfigsProvider = $paymentConfigsProvider;
    }

    public function createEventFromRequest(Request $request): StripeEventInterface
    {
        $configuredPaymentConfigs = $this->paymentConfigsProvider->getConfigs();

        $event = null;
        $paymentMethodConfig = null;

        // All configured Stripe Payment integrations should be iterated to find proper Stripe payment method.
        /** @var StripePaymentConfig $paymentConfig */
        foreach ($configuredPaymentConfigs as $paymentConfig) {
            try {
                $event = $this->constructEvent($request, $paymentConfig);
                $paymentMethodConfig = $paymentConfig;
                break;
            } catch (SignatureVerificationException $exception) {
                // skip handling because this event could be related to another configured Stripe payment integration.
            }
        }

        if (null === $event || null === $paymentMethodConfig) {
            throw new \LogicException(
                'There are no any configured Stripe payment methods available to handle event',
            );
        }

        $eventObject = $event->data->object;
        $responseObject = $this->createResponseObject($eventObject);

        return new StripeEvent($event->type, $paymentMethodConfig, $responseObject);
    }

    protected function createResponseObject($eventObject): ?ResponseObjectInterface
    {
        $type = get_class($eventObject);
        $responseObject = $this->responseTypeClassMapping[$type] ?? null;

        if ($responseObject) {
            return new $responseObject($eventObject->toArray());
        }

        return new UnsupportedResponse();
    }

    protected function constructEvent(Request $request, StripePaymentConfig $paymentConfig): ?Event
    {
        return \Stripe\Webhook::constructEvent(
            $request->getContent(),
            $request->server->get(self::STRIPE_SIGNATURE),
            $paymentConfig->getSigningSecret()
        );
    }
}
