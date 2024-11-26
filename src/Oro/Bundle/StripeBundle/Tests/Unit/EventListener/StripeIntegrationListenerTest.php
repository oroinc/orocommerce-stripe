<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\EventListener\StripeIntegrationListener;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider as CachedProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class StripeIntegrationListenerTest extends TestCase
{
    private const ORGANIZATION_ID = 8;

    private MockObject|CacheItemPoolInterface $cache;
    private StripeIntegrationListener $listener;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->listener = new StripeIntegrationListener($this->cache);
    }

    public function testPrePersistChannelWithClearCache(): void
    {
        $event = $this->createMock(LifecycleEventArgs::class);
        $channel = $this->getChannel();
        $channel->setType(StripeChannelType::TYPE);

        $this->assertRemoveCache();
        $this->listener->prePersistChannel($channel, $event);
    }

    public function testPrePersistChannelWithoutClearCache(): void
    {
        $event = $this->createMock(LifecycleEventArgs::class);
        $channel = $this->getChannel();

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $this->listener->prePersistChannel($channel, $event);
    }

    public function testPreUpdateChannelWithClearCache(): void
    {
        $event = $this->createMock(PreUpdateEventArgs::class);
        $event->expects($this->once())
            ->method('hasChangedField')
            ->with('enabled')
            ->willReturn(true);

        $channel = $this->getChannel();
        $channel->setType(StripeChannelType::TYPE);

        $this->assertRemoveCache();
        $this->listener->preUpdateChannel($channel, $event);
    }

    public function testPreUpdateChannelWithoutClearCache(): void
    {
        $event = $this->createMock(PreUpdateEventArgs::class);
        $channel = $this->getChannel();

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $this->listener->preUpdateChannel($channel, $event);
    }

    public function testPreRemoveChannelWithClearCache(): void
    {
        $event = $this->createMock(LifecycleEventArgs::class);
        $channel = $this->getChannel();
        $channel->setType(StripeChannelType::TYPE);

        $this->assertRemoveCache();
        $this->listener->preRemoveChannel($channel, $event);
    }

    public function testPreRemoveChannelWithoutClearCache(): void
    {
        $event = $this->createMock(LifecycleEventArgs::class);
        $channel = $this->getChannel();

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $this->listener->preRemoveChannel($channel, $event);
    }

    public function testPreUpdateSettingsWithClearCache(): void
    {
        $event = $this->createMock(PreUpdateEventArgs::class);

        $channel = $this->getChannel();

        $settings = new StripeTransportSettings();
        $settings->setChannel($channel);

        $event->expects($this->once())
            ->method('hasChangedField')
            ->with('userMonitoring')
            ->willReturn(true);

        $this->cache->expects($this->once())
            ->method('deleteItem')
            ->with(sprintf('%s|%d', CachedProvider::STRIPE_PAYMENT_MONITORING, self::ORGANIZATION_ID));

        $this->listener->preUpdateSettings($settings, $event);
    }

    public function testPreUpdateSettingsWithoutClearCache(): void
    {
        $event = $this->createMock(PreUpdateEventArgs::class);
        $settings = new StripeTransportSettings();

        $event->expects($this->once())
            ->method('hasChangedField')
            ->with('userMonitoring')
            ->willReturn(false);

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $this->listener->preUpdateSettings($settings, $event);
    }

    private function assertRemoveCache(): void
    {
        $this->cache
            ->expects($this->exactly(2))
            ->method('deleteItem')
            ->withConsecutive(
                [sprintf('%s|%d', CachedProvider::STRIPE_PAYMENT, self::ORGANIZATION_ID)],
                [sprintf('%s|%d', CachedProvider::STRIPE_PAYMENT_MONITORING, self::ORGANIZATION_ID)]
            );
    }

    private function getChannel(): Channel
    {
        $organization = new Organization();
        $organization->setId(self::ORGANIZATION_ID);

        $channel = new Channel();
        $channel->setOrganization($organization);

        return $channel;
    }
}
