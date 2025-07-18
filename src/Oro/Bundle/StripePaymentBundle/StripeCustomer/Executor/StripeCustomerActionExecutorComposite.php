<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor;

use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResult;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResultInterface;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;

/**
 * Performs the Stripe Customers API action by delegating calls to the inner executors.
 */
class StripeCustomerActionExecutorComposite implements StripeCustomerActionExecutorInterface
{
    /**
     * @param iterable<StripeCustomerActionExecutorInterface> $innerExecutors
     */
    public function __construct(private readonly iterable $innerExecutors)
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
    public function isApplicableForAction(StripeCustomerActionInterface $stripeAction): bool
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
        StripeCustomerActionInterface $stripeAction
    ): StripeCustomerActionResultInterface {
        foreach ($this->innerExecutors as $innerExecutor) {
            if ($innerExecutor->isApplicableForAction($stripeAction)) {
                try {
                    return $innerExecutor->executeAction($stripeAction);
                } catch (StripeExceptionInterface $stripeException) {
                    return new StripeCustomerActionResult(
                        successful: false,
                        stripeError: $stripeException
                    );
                }
            }
        }

        throw new \LogicException(
            sprintf('Action executor "%s" is not applicable', $stripeAction->getActionName())
        );
    }
}
