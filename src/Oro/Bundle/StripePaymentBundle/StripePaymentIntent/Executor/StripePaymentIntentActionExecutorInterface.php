<?php

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;

/**
 * Interface for the Stripe PaymentIntents API action executor.
 */
interface StripePaymentIntentActionExecutorInterface
{
    public function isSupportedByActionName(string $stripeActionName): bool;

    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool;

    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface;
}
