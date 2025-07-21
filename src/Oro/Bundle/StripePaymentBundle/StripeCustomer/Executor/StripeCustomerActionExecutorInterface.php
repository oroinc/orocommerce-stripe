<?php

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor;

use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResultInterface;

/**
 * Interface for the Stripe Customers API action executor.
 */
interface StripeCustomerActionExecutorInterface
{
    public function isSupportedByActionName(string $stripeActionName): bool;

    public function isApplicableForAction(StripeCustomerActionInterface $stripeAction): bool;

    public function executeAction(
        StripeCustomerActionInterface $stripeAction
    ): StripeCustomerActionResultInterface;
}
