<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;

/**
 * Stripe Customers API action model for the find-or-create action.
 */
class FindOrCreateStripeCustomerAction extends AbstractStripeCustomerAction
{
    public const string ACTION_NAME = 'customer_find_or_create';

    public function __construct(
        protected StripeClientConfigInterface $stripeClientConfig,
        protected PaymentTransaction $paymentTransaction
    ) {
        parent::__construct(static::ACTION_NAME, $this->stripeClientConfig, $this->paymentTransaction);
    }
}
