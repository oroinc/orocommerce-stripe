<?php

namespace Oro\Bundle\StripePaymentBundle\EventListener\PaymentCallback;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Event\AbstractCallbackEvent;
use Oro\Bundle\PaymentBundle\Event\PaymentCallbackListenerInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEventHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Handles the Stripe Event webhook.
 */
final class StripePaymentIntentsWebhookCallbackListener implements
    PaymentCallbackListenerInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private PaymentMethodProviderInterface $paymentMethodProvider,
        private PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function onPaymentCallback(AbstractCallbackEvent $event): void
    {
        if (!$event instanceof StripeWebhookEvent) {
            return;
        }

        $paymentTransaction = $event->getPaymentTransaction();
        if (!$paymentTransaction instanceof PaymentTransaction) {
            return;
        }

        $paymentMethodIdentifier = $paymentTransaction->getPaymentMethod();
        if (false === $this->paymentMethodProvider->hasPaymentMethod($paymentMethodIdentifier)) {
            $this->logMethodNotFound($event);

            return;
        }

        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentMethodIdentifier);
        if (!$paymentMethod instanceof StripeWebhookEventHandlerInterface) {
            $this->logMethodNotImplements($event);

            return;
        }

        try {
            $paymentMethod->onWebhookEvent($event);
        } catch (\Throwable $throwable) {
            $event->markFailed();

            $this->logUnexpectedError($event, $throwable);
        } finally {
            $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);
        }
    }

    private function logMethodNotFound(StripeWebhookEvent $event): void
    {
        /** @var PaymentTransaction $paymentTransaction */
        $paymentTransaction = $event->getPaymentTransaction();
        $this->logger
            ->notice(
                'Cannot process the webhook request for payment transaction #{paymentTransactionId}: '
                . 'payment method #{paymentMethodIdentifier} is not found',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                    'stripeEvent' => $event->getStripeEvent()->toArray(),
                ]
            );
    }

    private function logMethodNotImplements(StripeWebhookEvent $event): void
    {
        /** @var PaymentTransaction $paymentTransaction */
        $paymentTransaction = $event->getPaymentTransaction();
        $this->logger->error(
            'Failed to process the Stripe webhook request for payment transaction #{paymentTransactionId}: '
            . 'payment method #{paymentMethodIdentifier} does not implement {webhookHandlerInterface}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
                'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                'webhookHandlerInterface' => StripeWebhookEventHandlerInterface::class,
                'stripeEvent' => $event->getStripeEvent()->toArray(),
            ]
        );
    }

    private function logUnexpectedError(StripeWebhookEvent $event, \Throwable $throwable): void
    {
        /** @var PaymentTransaction $paymentTransaction */
        $paymentTransaction = $event->getPaymentTransaction();
        $this->logger->error(
            'Failed to process the Stripe webhook request for payment transaction #{paymentTransactionId}: {message}',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
                'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                'message' => $throwable->getMessage(),
                'stripeEvent' => $event->getStripeEvent()->toArray(),
                'throwable' => $throwable,
            ]
        );
    }
}
