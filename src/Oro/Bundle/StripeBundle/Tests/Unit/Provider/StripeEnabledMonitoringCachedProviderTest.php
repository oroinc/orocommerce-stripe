<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\StripeBundle\Entity\Repository\StripeTransportSettingsRepository;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class StripeEnabledMonitoringCachedProviderTest extends TestCase
{
    private FrontendHelper|MockObject $frontendHelper;
    private CacheItemPoolInterface|MockObject $cache;
    private ManagerRegistry|MockObject $manager;
    private StripeEnabledMonitoringCachedProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->frontendHelper = $this->createMock(FrontendHelper::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->manager = $this->createMock(ManagerRegistry::class);

        $this->provider = new StripeEnabledMonitoringCachedProvider(
            $this->frontendHelper,
            $this->cache,
            $this->manager
        );
    }

    public function testIsStripeMonitoringEnabledWithBackendRequest(): void
    {
        $this->frontendHelper->expects($this->once())
            ->method('isFrontendRequest')
            ->willReturn(false);

        $this->assertFalse($this->provider->isStripeMonitoringEnabled());
    }

    public function testIsStripeMonitoringEnabledWithDisableCache(): void
    {
        $this->frontendHelper->expects($this->once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn(StripeEnabledMonitoringCachedProvider::STRIPE_DISABLE);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with(StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING)
            ->willReturn($cacheItem);

        $this->assertFalse($this->provider->isStripeMonitoringEnabled());
    }

    public function testIsStripeMonitoringEnabledWithEnableCache(): void
    {
        $this->frontendHelper->expects($this->once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(true);

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn(StripeEnabledMonitoringCachedProvider::STRIPE_ENABLE);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with(StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING)
            ->willReturn($cacheItem);

        $this->assertTrue($this->provider->isStripeMonitoringEnabled());
    }

    public function testIsStripeMonitoringEnabledWithStripeIntegration(): void
    {
        $this->frontendHelper->expects($this->once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $cacheItem->expects($this->once())
            ->method('set')
            ->with(StripeEnabledMonitoringCachedProvider::STRIPE_ENABLE);

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn(StripeEnabledMonitoringCachedProvider::STRIPE_ENABLE);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with(StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING)
            ->willReturn($cacheItem);

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $repo = $this->createMock(StripeTransportSettingsRepository::class);
        $this->manager->expects($this->once())
            ->method('getRepository')
            ->with(StripeTransportSettings::class)
            ->willReturn($repo);

        $repo->expects($this->once())
            ->method('getEnabledMonitoringSettingsByType')
            ->with(StripeChannelType::TYPE)
            ->willReturn([new StripeTransportSettings()]);

        $this->assertTrue($this->provider->isStripeMonitoringEnabled());
    }

    public function testIsStripeMonitoringEnabledWithoutStripeIntegration(): void
    {
        $this->frontendHelper->expects($this->once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->expects($this->once())
            ->method('isHit')
            ->willReturn(false);

        $cacheItem->expects($this->once())
            ->method('set')
            ->with(StripeEnabledMonitoringCachedProvider::STRIPE_DISABLE);

        $cacheItem->expects($this->once())
            ->method('get')
            ->willReturn(StripeEnabledMonitoringCachedProvider::STRIPE_DISABLE);

        $this->cache->expects($this->once())
            ->method('getItem')
            ->with(StripeEnabledMonitoringCachedProvider::STRIPE_PAYMENT_MONITORING)
            ->willReturn($cacheItem);

        $this->cache->expects($this->once())
            ->method('save')
            ->with($cacheItem);

        $repo = $this->createMock(StripeTransportSettingsRepository::class);
        $this->manager->expects($this->once())
            ->method('getRepository')
            ->with(StripeTransportSettings::class)
            ->willReturn($repo);

        $repo->expects($this->once())
            ->method('getEnabledMonitoringSettingsByType')
            ->with(StripeChannelType::TYPE)
            ->willReturn([]);

        $this->assertFalse($this->provider->isStripeMonitoringEnabled());
    }
}
