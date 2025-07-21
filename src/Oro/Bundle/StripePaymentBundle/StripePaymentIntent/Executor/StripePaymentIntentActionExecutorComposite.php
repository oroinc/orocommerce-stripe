<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor;

use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;

/**
 * Performs the Stripe PaymentIntents API action by delegating calls to the inner executors.
 */
class StripePaymentIntentActionExecutorComposite implements StripePaymentIntentActionExecutorInterface
{
    /**
     * @param iterable<StripePaymentIntentActionExecutorInterface> $innerExecutors
     */
    public function __construct(private iterable $innerExecutors)
    {
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        foreach ($this->innerExecutors as $innerExecutor) {
            if ($innerExecutor->isSupportedByActionName($stripeActionName)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        foreach ($this->innerExecutors as $innerExecutor) {
            if ($innerExecutor->isApplicableForAction($stripeAction)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        foreach ($this->innerExecutors as $innerExecutor) {
            if ($innerExecutor->isApplicableForAction($stripeAction)) {
                try {
                    return $innerExecutor->executeAction($stripeAction);
                } catch (StripeExceptionInterface $stripeException) {
                    return new StripePaymentIntentActionResult(
                        successful: false,
                        stripeError: $stripeException
                    );
                }
            }
        }

        throw new \LogicException(
            sprintf('Payment method action "%s" is not applicable', $stripeAction->getActionName())
        );
    }
}
