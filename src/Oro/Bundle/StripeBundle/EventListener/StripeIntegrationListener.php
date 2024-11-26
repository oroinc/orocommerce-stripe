<?php

namespace Oro\Bundle\StripeBundle\EventListener;

use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Oro\Bundle\CacheBundle\Generator\UniversalCacheKeyGenerator;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\OrganizationBundle\Entity\OrganizationInterface;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Remove {@link StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING} cache key when:
 * - Updated enabled field for {@link Channel}.
 * - Updated userMonitoring field for {@link StripeTransportSettings}.
 */
class StripeIntegrationListener
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function prePersistChannel(Channel $channel, LifecycleEventArgs $event): void
    {
        if ($channel->getType() === StripeChannelType::TYPE) {
            $this->clearStripePaymentCache($channel->getOrganization());
            $this->clearStripeMonitoringCache($channel->getOrganization());
        }
    }

    public function preUpdateChannel(Channel $channel, PreUpdateEventArgs $event): void
    {
        if ($channel->getType() === StripeChannelType::TYPE && $event->hasChangedField('enabled')) {
            $this->clearStripePaymentCache($channel->getOrganization());
            $this->clearStripeMonitoringCache($channel->getOrganization());
        }
    }

    public function preRemoveChannel(Channel $channel, LifecycleEventArgs $event): void
    {
        if ($channel->getType() === StripeChannelType::TYPE) {
            $this->clearStripePaymentCache($channel->getOrganization());
            $this->clearStripeMonitoringCache($channel->getOrganization());
        }
    }

    public function preUpdateSettings(StripeTransportSettings $settings, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('userMonitoring')) {
            $channel = $settings->getChannel();
            $this->clearStripeMonitoringCache($channel->getOrganization());
        }
    }

    private function clearStripePaymentCache(OrganizationInterface $organization): void
    {
        $cacheKey = $this->getCacheKey(
            StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT,
            $organization->getId()
        );

        $this->cache->deleteItem($cacheKey);
    }

    private function clearStripeMonitoringCache(OrganizationInterface $organization): void
    {
        $cacheKey = $this->getCacheKey(
            StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING,
            $organization->getId()
        );

        $this->cache->deleteItem($cacheKey);
    }

    private function getCacheKey(string $key, int $organizationId): string
    {
        return UniversalCacheKeyGenerator::normalizeCacheKey(
            sprintf('%s|%d', $key, $organizationId)
        );
    }
}
