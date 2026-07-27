<?php

namespace Oro\Bundle\StripePaymentBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripePaymentElementRouteAwareStripeScriptProvider;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Deletes cache items created by {@see StripePaymentElementRouteAwareStripeScriptProvider}.
 */
final class DeleteStripePaymentElementRouteAwareScriptCacheDoctrineListener implements ResetInterface
{
    /** @var array<int,int> */
    private array $affectedOrganizationIds = [];

    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        $this->handleEntityInsertions($unitOfWork);
        $this->handleEntityUpdates($unitOfWork);
        $this->handleEntityDeletions($unitOfWork);
    }

    private function handleEntityInsertions(UnitOfWork $unitOfWork): void
    {
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Channel && $entity->getTransport() instanceof StripePaymentElementSettings) {
                $channel = $entity;
                $settings = $entity->getTransport();
            } elseif ($entity instanceof StripePaymentElementSettings) {
                $channel = $entity->getChannel();
                $settings = $entity;
            } else {
                continue;
            }

            if ($channel?->isEnabled()) {
                $organizationId = (int) $channel->getOrganization()?->getId();
                $this->affectedOrganizationIds[$organizationId] = $organizationId;
            }
        }
    }

    private function handleEntityUpdates(UnitOfWork $unitOfWork): void
    {
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Channel && $entity->getTransport() instanceof StripePaymentElementSettings) {
                $channel = $entity;
            } else {
                continue;
            }

            $changeset = $unitOfWork->getEntityChangeSet($channel);
            if (!empty($changeset['enabled'])) {
                $organizationId = (int) $channel->getOrganization()?->getId();
                $this->affectedOrganizationIds[$organizationId] = $organizationId;
            }
        }
    }

    private function handleEntityDeletions(UnitOfWork $unitOfWork): void
    {
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof StripePaymentElementSettings) {
                $organizationId = (int) $entity->getChannel()?->getOrganization()?->getId();
                $this->affectedOrganizationIds[$organizationId] = $organizationId;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        try {
            foreach ($this->affectedOrganizationIds as $affectedOrganizationId) {
                $this->cache->delete(
                    StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptEnabledCacheKey(
                        $affectedOrganizationId
                    )
                );
                $this->cache->delete(
                    StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptVersionCacheKey(
                        $affectedOrganizationId
                    )
                );
            }
        } finally {
            $this->affectedOrganizationIds = [];
        }
    }

    public function onClear(): void
    {
        $this->affectedOrganizationIds = [];
    }

    #[\Override]
    public function reset(): void
    {
        $this->affectedOrganizationIds = [];
    }
}
