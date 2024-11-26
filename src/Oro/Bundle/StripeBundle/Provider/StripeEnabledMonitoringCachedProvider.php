<?php

namespace Oro\Bundle\StripeBundle\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CacheBundle\Generator\UniversalCacheKeyGenerator;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
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
    public const STRIPE_PAYMENT  = 'stripe_payment';
    public const STRIPE_PAYMENT_MONITORING  = 'stripe_payment_monitoring';
    public const STRIPE_ENABLE  = 'stripe_enabled';
    public const STRIPE_DISABLE = 'stripe_disabled';

    private FrontendHelper $frontendHelper;
    private CacheItemPoolInterface $cache;
    private ManagerRegistry $manager;
    private ?TokenAccessorInterface $tokenAccessor = null;

    public function __construct(FrontendHelper $frontendHelper, CacheItemPoolInterface $cache, ManagerRegistry $manager)
    {
        $this->frontendHelper = $frontendHelper;
        $this->cache = $cache;
        $this->manager = $manager;
    }

    public function setTokenAccessor(TokenAccessorInterface $tokenAccessor): self
    {
        $this->tokenAccessor = $tokenAccessor;

        return $this;
    }

    public function isStripeEnabled(): bool
    {
        if (!$this->frontendHelper->isFrontendRequest()) {
            return false;
        }

        $stripeCacheItem = $this->cache->getItem($this->getCacheKey(self::STRIPE_PAYMENT));
        if (!$stripeCacheItem->isHit()) {
            $stripeCacheValue = $this->isEnabledInSettings() ? self::STRIPE_ENABLE : self::STRIPE_DISABLE;
            $stripeCacheItem->set($stripeCacheValue);
            $this->cache->save($stripeCacheItem);
        }

        return $stripeCacheItem->get() === self::STRIPE_ENABLE;
    }

    public function isStripeMonitoringEnabled(): bool
    {
        if (!$this->frontendHelper->isFrontendRequest()) {
            return false;
        }

        $stripeCacheItem = $this->cache->getItem($this->getCacheKey(self::STRIPE_PAYMENT_MONITORING));
        if (!$stripeCacheItem->isHit()) {
            $stripeCacheValue = $this->isMonitoringEnabledInSettings() ? self::STRIPE_ENABLE : self::STRIPE_DISABLE;
            $stripeCacheItem->set($stripeCacheValue);
            $this->cache->save($stripeCacheItem);
        }

        return $stripeCacheItem->get() === self::STRIPE_ENABLE;
    }

    private function isEnabledInSettings(): bool
    {
        $settings = $this->manager
            ->getRepository(StripeTransportSettings::class)
            ->getEnabledSettingsByType(StripeChannelType::TYPE);

        return !empty($settings);
    }

    private function isMonitoringEnabledInSettings(): bool
    {
        $settings = $this->manager
            ->getRepository(StripeTransportSettings::class)
            ->getEnabledMonitoringSettingsByType(StripeChannelType::TYPE);

        return !empty($settings);
    }

    private function getCacheKey(string $key): string
    {
        return UniversalCacheKeyGenerator::normalizeCacheKey(
            sprintf('%s|%d', $key, $this->tokenAccessor->getOrganizationId())
        );
    }
}
