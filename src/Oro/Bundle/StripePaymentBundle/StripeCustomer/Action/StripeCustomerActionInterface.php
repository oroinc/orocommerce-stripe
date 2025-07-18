<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Interface for the Stripe Customers API action model.
 */
interface StripeCustomerActionInterface
{
    public function getActionName(): string;

    public function getStripeClientConfig(): StripeClientConfigInterface;

    public function getPaymentTransaction(): PaymentTransaction;
}
