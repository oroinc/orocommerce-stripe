<?php

namespace Oro\Bundle\StripeBundle\Event;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentAwareInterface;
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
    private ManagerRegistry $managerRegistry;

    protected array $responseTypeClassMapping = [
        PaymentIntent::class => PaymentIntentResponse::class,
        Balance::class => PaymentIntentResponse::class,
        Charge::class => ChargeResponse::class,
        Customer::class => CustomerResponse::class,
        Refund::class => RefundResponse::class,
        SetupIntent::class => SetupIntentResponse::class,
    ];

    public function __construct(StripePaymentConfigsProvider $paymentConfigsProvider, ManagerRegistry $managerRegistry)
    {
        $this->paymentConfigsProvider = $paymentConfigsProvider;
        $this->managerRegistry = $managerRegistry;
    }

    #[\Override]
    public function createEventFromRequest(Request $request): StripeEventInterface
    {
        $configuredPaymentConfigs = $this->paymentConfigsProvider->getConfigs();

        $event = null;
        $paymentMethodConfig = null;
        $transactionPaymentMethod = null;

        // All configured Stripe Payment integrations should be iterated to find proper Stripe payment method.
        /** @var StripePaymentConfig $paymentConfig */
        foreach ($configuredPaymentConfigs as $paymentConfig) {
            $event = null;

            try {
                $event = $this->constructEvent($request, $paymentConfig);
                $paymentMethodConfig = $paymentConfig;
            } catch (SignatureVerificationException $exception) {
                // skip handling because this event could be related to another configured Stripe payment integration.
            }

            if (null === $event) {
                continue;
            }

            $eventObject = $event->data->object;
            $responseObject = $this->createResponseObject($eventObject);

            if (null === $transactionPaymentMethod) {
                $transactionPaymentMethod = $this->getTransactionPaymentMethod($responseObject);
            }

            if (empty($transactionPaymentMethod)) {
                continue;
            }

            if ($transactionPaymentMethod === $paymentMethodConfig->getPaymentMethodIdentifier()) {
                break;
            }
        }

        if (null === $event || null === $paymentMethodConfig) {
            throw new \LogicException(
                'There are no any configured Stripe payment methods available to handle event',
            );
        }

        return new StripeEvent($event->type, $paymentMethodConfig, $responseObject);
    }

    protected function getTransactionPaymentMethod(?ResponseObjectInterface $responseObject): ?string
    {
        if (!$responseObject instanceof PaymentIntentAwareInterface
            || empty($responseObject->getPaymentIntentId())
        ) {
            return null;
        }

        $paymentTransaction = $this->managerRegistry->getRepository(PaymentTransaction::class)
            ->findOneBy(['reference' => $responseObject->getPaymentIntentId()]);

        return $paymentTransaction->getPaymentMethod();
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
