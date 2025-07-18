<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Stripe\Event as StripeEvent;

/**
 * Stripe PaymentIntents API action model aware of {@see StripeEvent}.
 */
class StripePaymentIntentWebhookAction implements StripePaymentIntentWebhookActionInterface
{
    public function __construct(
        private readonly StripeEvent $stripeEvent,
        private readonly StripeClientConfigInterface&StripePaymentIntentConfigInterface $stripePaymentIntentConfig,
        private readonly PaymentTransaction $paymentTransaction,
    ) {
    }

    #[\Override]
    public function getActionName(): string
    {
        return 'webhook:' . $this->stripeEvent->type;
    }

    #[\Override]
    public function getPaymentTransaction(): PaymentTransaction
    {
        return $this->paymentTransaction;
    }

    #[\Override]
    public function getStripeClientConfig(): StripeClientConfigInterface
    {
        return $this->stripePaymentIntentConfig;
    }

    #[\Override]
    public function getPaymentIntentConfig(): StripePaymentIntentConfigInterface
    {
        return $this->stripePaymentIntentConfig;
    }

    #[\Override]
    public function getStripeEvent(): StripeEvent
    {
        return $this->stripeEvent;
    }
}
