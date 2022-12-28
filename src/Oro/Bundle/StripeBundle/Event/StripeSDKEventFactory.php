<?php

namespace Oro\Bundle\StripeBundle\Event;

use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Stripe\Charge;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
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
        Charge::class => ChargeResponse::class
    ];

    public function __construct(StripePaymentConfigsProvider $paymentConfigsProvider)
    {
        $this->paymentConfigsProvider = $paymentConfigsProvider;
    }

    public function createEventFromRequest(Request $request): StripeEventInterface
    {
        $data = $request->getContent();
        $configuredPaymentConfigs = $this->paymentConfigsProvider->getConfigs();

        $event = null;
        $paymentMethodConfig = null;

        // All configured Stripe Payment integrations should be iterated to find proper Stripe payment method.
        /** @var StripePaymentConfig $paymentConfig */
        foreach ($configuredPaymentConfigs as $paymentConfig) {
            try {
                $event = \Stripe\Webhook::constructEvent(
                    $data,
                    $request->server->get(self::STRIPE_SIGNATURE),
                    $paymentConfig->getSigningSecret()
                );
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

        if (null === $responseObject) {
            throw new \LogicException(sprintf(
                'Received object Type %s is not supported by Stripe Integration.',
                get_class($eventObject)
            ));
        }

        return new StripeEvent($event->type, $paymentMethodConfig, $responseObject);
    }

    protected function createResponseObject($eventObject): ?ResponseObjectInterface
    {
        $type = get_class($eventObject);
        $responseObject = $this->responseTypeClassMapping[$type] ?? null;

        if (null === $responseObject) {
            throw new \LogicException(sprintf('"%s" response type is not supported', $type));
        }

        return new $responseObject($eventObject->toArray());
    }
}
