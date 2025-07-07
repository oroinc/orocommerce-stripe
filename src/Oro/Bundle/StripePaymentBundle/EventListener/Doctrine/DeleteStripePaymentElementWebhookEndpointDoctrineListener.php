<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\StripeWebhookEndpointActionExecutorInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Deletes the Stripe WebhookEndpoint associated with the deleted Stripe Payment Element integration.
 */
final class DeleteStripePaymentElementWebhookEndpointDoctrineListener implements ResetInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** @var array<string,StripePaymentElementSettings> */
    private array $deletedTransportSettings = [];

    public function __construct(
        private readonly StripePaymentElementConfigFactory $stripePaymentElementConfigFactory,
        private readonly StripeWebhookEndpointActionExecutorInterface $stripeWebhookEndpointActionExecutor
    ) {
        $this->logger = new NullLogger();
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof StripePaymentElementSettings) {
                $this->deletedTransportSettings[spl_object_hash($entity)] = $entity;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        foreach ($this->deletedTransportSettings as $stripePaymentElementSettings) {
            try {
                $stripePaymentElementConfig = $this->stripePaymentElementConfigFactory
                    ->createConfig($stripePaymentElementSettings);
                $this->stripeWebhookEndpointActionExecutor->executeAction(
                    new DeleteStripeWebhookEndpointAction($stripePaymentElementConfig)
                );
            } catch (\Throwable $throwable) {
                $this->logger
                    ->error(
                        'Failed to delete the Stripe Webhook Endpoint for #{transportSettingsId}: {message}',
                        [
                            'transportSettingsId' => $stripePaymentElementSettings->getId(),
                            'message' => $throwable->getMessage(),
                            'throwable' => $throwable,
                        ]
                    );
            }
        }

        $this->deletedTransportSettings = [];
    }

    public function onClear(): void
    {
        $this->deletedTransportSettings = [];
    }

    #[\Override]
    public function reset(): void
    {
        $this->deletedTransportSettings = [];
    }
}
