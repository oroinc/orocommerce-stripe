<?php

namespace Oro\Bundle\StripePaymentBundle\Controller;

use Oro\Bundle\PaymentBundle\Event\CallbackHandler;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Config\StripeWebhookEndpointConfigProviderInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Factory\StripeCallbackWebhookEventFactoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles Stripe Event webhooks for payment processing to keep payment transactions synchronized with Stripe.
 */
final class StripePaymentWebhookController extends AbstractController
{
    /**
     * @param string $webhookAccessId ID to associate the incoming Stripe webhooks requests
     *  to a specific Stripe payment integration.
     * @param Request $request
     *
     * @return Response
     */
    public function __invoke(string $webhookAccessId, Request $request): Response
    {
        /** @var StripeWebhookEndpointConfigProviderInterface $stripeWebhookEndpointConfigProvider */
        $stripeWebhookEndpointConfigProvider = $this->container
            ->get(StripeWebhookEndpointConfigProviderInterface::class);
        $webhookEndpointConfig = $stripeWebhookEndpointConfigProvider->getStripeWebhookEndpointConfig($webhookAccessId);
        if (!$webhookEndpointConfig) {
            throw $this->createNotFoundException();
        }

        /** @var StripeCallbackWebhookEventFactoryInterface $stripeCallbackWebhookEventFactory */
        $stripeCallbackWebhookEventFactory = $this->container->get(StripeCallbackWebhookEventFactoryInterface::class);
        $stripeCallbackWebhookEvent = $stripeCallbackWebhookEventFactory->createStripeCallbackWebhookEvent(
            $webhookEndpointConfig,
            $request->getContent(),
            (string)$request->server->get('HTTP_STRIPE_SIGNATURE')
        );

        if ($stripeCallbackWebhookEvent === null) {
            throw $this->createNotFoundException();
        }

        /** @var CallbackHandler $paymentCallbackHandler */
        $paymentCallbackHandler = $this->container->get(CallbackHandler::class);

        return $paymentCallbackHandler->handle($stripeCallbackWebhookEvent);
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return [
            ...parent::getSubscribedServices(),
            StripeWebhookEndpointConfigProviderInterface::class,
            StripeCallbackWebhookEventFactoryInterface::class,
            CallbackHandler::class,
        ];
    }
}
