<?php

namespace Oro\Bundle\StripeBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
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
            $this->clearStripeMonitoringCache();
        }
    }

    public function preUpdateChannel(Channel $channel, PreUpdateEventArgs $event): void
    {
        if ($channel->getType() === StripeChannelType::TYPE && $event->hasChangedField('enabled')) {
            $this->clearStripeMonitoringCache();
        }
    }

    public function preUpdateSettings(StripeTransportSettings $settings, PreUpdateEventArgs $event): void
    {
        if ($event->hasChangedField('userMonitoring')) {
            $this->clearStripeMonitoringCache();
        }
    }

    private function clearStripeMonitoringCache(): void
    {
        $this->cache->deleteItem(StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING);
    }
}
