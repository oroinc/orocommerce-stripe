<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Factory;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider\PaymentTransactionByStripeEventProviderInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\Webhook as StripeWebhook;

/**
 * Creates the {@see StripeWebhookEvent} for the given webhook payload and webhook signature.
 *
 * The created event can be dispatched via {@see CallbackHandler}.
 */
class StripeCallbackWebhookEventFactory implements
    StripeCallbackWebhookEventFactoryInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private PaymentTransactionByStripeEventProviderInterface $paymentTransactionByStripeEventProvider
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function createStripeCallbackWebhookEvent(
        StripeWebhookEndpointConfigInterface $stripeWebhookEndpointConfig,
        string $webhookPayload,
        string $webhookSignature,
        int $tolerance = StripeWebhook::DEFAULT_TOLERANCE
    ): ?StripeWebhookEvent {
        try {
            $stripeEvent = StripeWebhook::constructEvent(
                $webhookPayload,
                $webhookSignature,
                $stripeWebhookEndpointConfig->getWebhookSecret(),
                $tolerance
            );

            $paymentTransaction = $this->paymentTransactionByStripeEventProvider
                ->findPaymentTransactionByStripeEvent($stripeEvent);

            if ($paymentTransaction === null) {
                $this->logNoPaymentTransaction($stripeEvent);

                return null;
            }

            $event = new StripeWebhookEvent($stripeEvent);
            $event->setPaymentTransaction($paymentTransaction);

            return $event;
        } catch (\Throwable $throwable) {
            $this->logFailedToCreateEvent($throwable, $stripeWebhookEndpointConfig, $webhookPayload);

            return null;
        }
    }

    private function logNoPaymentTransaction(\Stripe\Event $stripeEvent): void
    {
        $this->logger
            ->notice(
                'Failed to create a StripeWebhookEvent from request: '
                . 'payment transaction is not found for Stripe Event #{stripeEventId}',
                [
                    'stripeEventId' => $stripeEvent->id,
                    'stripeEvent' => $stripeEvent->toArray(),
                ]
            );
    }

    private function logFailedToCreateEvent(
        \Throwable|\Exception $throwable,
        StripeWebhookEndpointConfigInterface $stripeWebhookEndpointConfig,
        string $webhookPayload
    ): void {
        $this->logger->error(
            'Failed to create a StripeWebhookEvent from request: {message}',
            [
                'message' => $throwable->getMessage(),
                'throwable' => $throwable,
                'webhookAccessId' => $stripeWebhookEndpointConfig->getWebhookAccessId(),
                'webhookPayload' => $webhookPayload,
            ]
        );
    }
}
