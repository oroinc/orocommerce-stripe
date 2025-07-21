<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor;

use Oro\Bundle\StripePaymentBundle\Event\StripeWebhookEndpointActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\CreateOrUpdateStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResultInterface;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Creates or updates a Stripe WebhookEndpoint.
 */
class CreateOrUpdateStripeActionExecutor implements StripeWebhookEndpointActionExecutorInterface
{
    public function __construct(
        private readonly StripeClientFactoryInterface $stripeClientFactory,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === CreateOrUpdateStripeWebhookEndpointAction::ACTION_NAME;
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
        $stripeClient = $this->stripeClientFactory->createStripeClient($stripeAction->getStripeClientConfig());
        $stripeWebhookConfig = $stripeAction->getStripeWebhookConfig();

        if ($stripeWebhookConfig->getWebhookStripeId()) {
            try {
                $requestArgs = [$stripeWebhookConfig->getWebhookStripeId()];

                $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
                    $stripeAction,
                    'webhookEndpointsRetrieve',
                    $requestArgs
                );
                $this->eventDispatcher->dispatch($beforeRequestEvent);

                $stripeWebhookEndpoint = $stripeClient->webhookEndpoints->retrieve(
                    ...$beforeRequestEvent->getRequestArgs()
                );
            } catch (StripeApiErrorException $exception) {
                if ($exception->getHttpStatus() === 404) {
                    $stripeWebhookEndpoint = null;
                } else {
                    throw $exception;
                }
            }
        }

        if (!isset($stripeWebhookEndpoint)) {
            $requestArgs = [
                [
                    'url' => $stripeWebhookConfig->getWebhookUrl(),
                    'enabled_events' => $stripeWebhookConfig->getWebhookEvents(),
                    'description' => $stripeWebhookConfig->getWebhookDescription(),
                    'metadata' => $stripeWebhookConfig->getWebhookMetadata(),
                ]
            ];

            $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
                $stripeAction,
                'webhookEndpointsCreate',
                $requestArgs
            );
            $this->eventDispatcher->dispatch($beforeRequestEvent);

            $stripeWebhookEndpoint = $stripeClient->webhookEndpoints->create(...$beforeRequestEvent->getRequestArgs());
        } else {
            $requestArgs = [
                $stripeWebhookConfig->getWebhookStripeId(),
                [
                    'url' => $stripeWebhookConfig->getWebhookUrl(),
                    'enabled_events' => $stripeWebhookConfig->getWebhookEvents(),
                    'description' => $stripeWebhookConfig->getWebhookDescription(),
                    'metadata' => $stripeWebhookConfig->getWebhookMetadata(),
                ]
            ];

            $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
                $stripeAction,
                'webhookEndpointsUpdate',
                $requestArgs
            );
            $this->eventDispatcher->dispatch($beforeRequestEvent);

            $stripeWebhookEndpoint = $stripeClient->webhookEndpoints->update(...$beforeRequestEvent->getRequestArgs());
        }

        return new StripeWebhookEndpointActionResult(successful: true, stripeWebhookEndpoint: $stripeWebhookEndpoint);
    }
}
