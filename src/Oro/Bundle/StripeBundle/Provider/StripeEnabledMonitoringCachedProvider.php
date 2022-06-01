<?php

namespace Oro\Bundle\StripeBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Provider for check possibility to show stripe_script.js.twig on all frontend pages.
 * JS script will be displayed when:
 * - Stripe integration is active.
 * - Stripe User Monitoring is enabled.
 * - The current page is the frontend.
 */
class StripeEnabledMonitoringCachedProvider
{
    public const STRIPE_PAYMENT_MONITORING  = 'stripe_payment_monitoring';
    public const STRIPE_ENABLE  = 'stripe_enabled';
    public const STRIPE_DISABLE = 'stripe_disabled';

    private FrontendHelper $frontendHelper;
    private CacheItemPoolInterface $cache;
    private ManagerRegistry $manager;

    public function __construct(FrontendHelper $frontendHelper, CacheItemPoolInterface $cache, ManagerRegistry $manager)
    {
        $this->frontendHelper = $frontendHelper;
        $this->cache = $cache;
        $this->manager = $manager;
    }

    public function isStripeMonitoringEnabled(): bool
    {
        if (!$this->frontendHelper->isFrontendRequest()) {
            return false;
        }

        $stripeCacheItem = $this->cache->getItem(self::STRIPE_PAYMENT_MONITORING);
        if (!$stripeCacheItem->isHit()) {
            $stripeCacheValue = $this->isMonitoringEnabledInSettings() ? self::STRIPE_ENABLE : self::STRIPE_DISABLE;
            $stripeCacheItem->set($stripeCacheValue);
            $this->cache->save($stripeCacheItem);
        }

        return $stripeCacheItem->get() === self::STRIPE_ENABLE;
    }

    private function isMonitoringEnabledInSettings(): bool
    {
        $settings = $this->manager
            ->getRepository(StripeTransportSettings::class)
            ->getEnabledMonitoringSettingsByType(StripeChannelType::TYPE);

        return !empty($settings);
    }
}
