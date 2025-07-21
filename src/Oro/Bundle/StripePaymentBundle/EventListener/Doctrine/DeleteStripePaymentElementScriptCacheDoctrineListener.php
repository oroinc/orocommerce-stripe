<?php

namespace Oro\Bundle\StripePaymentBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripePaymentElementStripeScriptProvider;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Deletes cache items created by {@see StripePaymentElementStripeScriptProvider}.
 */
final class DeleteStripePaymentElementScriptCacheDoctrineListener implements ResetInterface
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
            if ($entity instanceof StripePaymentElementSettings && $entity->isUserMonitoringEnabled()) {
                $organizationId = $entity->getChannel()?->getOrganization()?->getId();
                $this->affectedOrganizationIds[$organizationId] = $organizationId;
            }
        }
    }

    private function handleEntityUpdates(UnitOfWork $unitOfWork): void
    {
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof StripePaymentElementSettings) {
                $changeset = $unitOfWork->getEntityChangeSet($entity);
                if (!empty($changeset['userMonitoringEnabled'])) {
                    $organizationId = $entity->getChannel()?->getOrganization()?->getId();
                    $this->affectedOrganizationIds[$organizationId] = $organizationId;
                }
            }
        }
    }

    private function handleEntityDeletions(UnitOfWork $unitOfWork): void
    {
        foreach ($unitOfWork->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof StripePaymentElementSettings && $entity->isUserMonitoringEnabled()) {
                $organizationId = $entity->getChannel()?->getOrganization()?->getId();
                $this->affectedOrganizationIds[$organizationId] = $organizationId;
            }
        }
    }

    public function postFlush(PostFlushEventArgs $eventArgs): void
    {
        try {
            foreach ($this->affectedOrganizationIds as $affectedOrganizationId) {
                $this->cache->delete(
                    StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(
                        $affectedOrganizationId
                    )
                );
                $this->cache->delete(
                    StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(
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
