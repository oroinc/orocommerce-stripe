<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Stripe\Event as StripeEvent;

/**
 * Finds the payment transaction associated with the given Stripe event by delegating calls to inner providers.
 */
class PaymentTransactionByStripeEventProvider implements PaymentTransactionByStripeEventProviderInterface
{
    /**
     * @param iterable<PaymentTransactionByStripeEventProviderInterface> $providers
     */
    public function __construct(private readonly iterable $providers)
    {
    }

    #[\Override]
    public function isApplicable(StripeEvent $event): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isApplicable($event)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function findPaymentTransactionByStripeEvent(StripeEvent $event): ?PaymentTransaction
    {
        foreach ($this->providers as $provider) {
            if ($provider->isApplicable($event)) {
                return $provider->findPaymentTransactionByStripeEvent($event);
            }
        }

        return null;
    }
}
