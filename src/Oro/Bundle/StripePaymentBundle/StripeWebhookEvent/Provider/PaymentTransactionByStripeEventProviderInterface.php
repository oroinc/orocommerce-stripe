<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Stripe\Event as StripeEvent;

/**
 * Finds the payment transaction associated with the given Stripe event.
 */
interface PaymentTransactionByStripeEventProviderInterface
{
    /**
     * Finds a payment transaction associated with the given Stripe event.
     */
    public function findPaymentTransactionByStripeEvent(StripeEvent $event): ?PaymentTransaction;

    /**
     * Determines whether this provider is applicable for the given Stripe event.
     */
    public function isApplicable(StripeEvent $event): bool;
}
