<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor;

use Oro\Bundle\StripePaymentBundle\Event\StripeWebhookEndpointActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResultInterface;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Deletes a Stripe WebhookEndpoint.
 */
class DeleteStripeActionExecutor implements StripeWebhookEndpointActionExecutorInterface
{
    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === DeleteStripeWebhookEndpointAction::ACTION_NAME;
    }

    #[\Override]
    public function isApplicableForAction(StripeWebhookEndpointActionInterface $stripeAction): bool
    {
        return $this->isSupportedByActionName($stripeAction->getActionName());
    }

    #[\Override]
    public function executeAction(
        StripeWebhookEndpointActionInterface $stripeAction
    ): StripeWebhookEndpointActionResultInterface {
        $stripeWebhookConfig = $stripeAction->getStripeWebhookConfig();
        $webhookStripeId = $stripeWebhookConfig->getWebhookStripeId();
        if (!$webhookStripeId) {
            return new StripeWebhookEndpointActionResult(successful: true);
        }

        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());

        try {
            $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
                $stripeAction,
                'webhookEndpointsDelete',
                [$webhookStripeId]
            );
            $this->eventDispatcher->dispatch($beforeRequestEvent);

            $stripeWebhookEndpoint = $stripeClient->webhookEndpoints->delete(...$beforeRequestEvent->getRequestArgs());
        } catch (StripeApiErrorException $apiErrorException) {
            if ($apiErrorException->getHttpStatus() === 404) {
                return new StripeWebhookEndpointActionResult(successful: false);
            }

            throw $apiErrorException;
        }

        return new StripeWebhookEndpointActionResult(successful: true, stripeWebhookEndpoint: $stripeWebhookEndpoint);
    }
}
