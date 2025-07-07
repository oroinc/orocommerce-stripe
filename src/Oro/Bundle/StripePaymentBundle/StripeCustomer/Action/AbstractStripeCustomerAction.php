<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Base Stripe Customers API action model.
 */
abstract class AbstractStripeCustomerAction implements StripeCustomerActionInterface
{
    public function __construct(
        protected string $actionName,
        protected StripeClientConfigInterface $stripeClientConfig,
        protected PaymentTransaction $paymentTransaction
    ) {
    }

    #[\Override]
    public function getActionName(): string
    {
        return $this->actionName;
    }

    #[\Override]
    public function getStripeClientConfig(): StripeClientConfigInterface
    {
        return $this->stripeClientConfig;
    }

    #[\Override]
    public function getPaymentTransaction(): PaymentTransaction
    {
        return $this->paymentTransaction;
    }
}
