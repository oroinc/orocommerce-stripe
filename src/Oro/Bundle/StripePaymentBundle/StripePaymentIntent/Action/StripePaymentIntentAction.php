<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Stripe PaymentIntents API action model.
 */
class StripePaymentIntentAction implements StripePaymentIntentActionInterface
{
    public function __construct(
        private readonly string $actionName,
        private readonly StripeClientConfigInterface&StripePaymentIntentConfigInterface $stripePaymentIntentConfig,
        private readonly PaymentTransaction $paymentTransaction
    ) {
    }

    #[\Override]
    public function getActionName(): string
    {
        return $this->actionName;
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
}
