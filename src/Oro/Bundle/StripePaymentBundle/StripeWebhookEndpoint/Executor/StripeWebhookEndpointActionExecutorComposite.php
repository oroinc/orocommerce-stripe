<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResultInterface;
use Stripe\Exception\ExceptionInterface as StripeExceptionInterface;

/**
 * Performs the Stripe WebhookEndpoints API action by delegating calls to the inner executors.
 */
class StripeWebhookEndpointActionExecutorComposite implements StripeWebhookEndpointActionExecutorInterface
{
    /**
     * @param iterable<StripeWebhookEndpointActionExecutorInterface> $innerExecutors
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
    public function isApplicableForAction(StripeWebhookEndpointActionInterface $stripeAction): bool
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
        StripeWebhookEndpointActionInterface $stripeAction
    ): StripeWebhookEndpointActionResultInterface {
        foreach ($this->innerExecutors as $innerExecutor) {
            if ($innerExecutor->isApplicableForAction($stripeAction)) {
                try {
                    return $innerExecutor->executeAction($stripeAction);
                } catch (StripeExceptionInterface $stripeException) {
                    return new StripeWebhookEndpointActionResult(
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
