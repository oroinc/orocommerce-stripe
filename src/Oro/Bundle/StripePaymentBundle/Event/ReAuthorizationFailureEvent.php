<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Event;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a payment re-authorization fails.
 */
class ReAuthorizationFailureEvent extends Event
{
    public function __construct(
        private readonly PaymentTransaction $paymentTransaction,
        private readonly array $paymentMethodResult
    ) {
    }

    public function getPaymentTransaction(): PaymentTransaction
    {
        return $this->paymentTransaction;
    }

    public function getPaymentMethodResult(): array
    {
        return $this->paymentMethodResult;
    }
}
