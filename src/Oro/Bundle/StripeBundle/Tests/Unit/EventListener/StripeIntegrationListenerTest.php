<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\EventListener\StripeIntegrationListener;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

class StripeIntegrationListenerTest extends TestCase
{
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CacheItemPoolInterface
     */
    private CacheItemPoolInterface $cache;
    private StripeIntegrationListener $listener;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheItemPoolInterface::class);
        $this->listener = new StripeIntegrationListener($this->cache);
    }

    public function testPrePersistChannelWithClearCache(): void
    {
        $event = $this->createMock(LifecycleEventArgs::class);
        $channel = new Channel();
        $channel->setType(StripeChannelType::TYPE);

        $this->cache->expects($this->once())
            ->method('deleteItem');

        $this->listener->prePersistChannel($channel, $event);
    }

    public function testPrePersistChannelWithoutClearCache(): void
    {
        $event = $this->createMock(LifecycleEventArgs::class);
        $channel = new Channel();

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

        $channel = new Channel();
        $channel->setType(StripeChannelType::TYPE);

        $this->cache->expects($this->once())
            ->method('deleteItem');

        $this->listener->preUpdateChannel($channel, $event);
    }

    public function testPreUpdateChannelWithoutClearCache(): void
    {
        $event = $this->createMock(PreUpdateEventArgs::class);
        $channel = new Channel();

        $this->cache->expects($this->never())
            ->method('deleteItem');

        $this->listener->preUpdateChannel($channel, $event);
    }

    public function testPreUpdateSettingsWithClearCache(): void
    {
        $event = $this->createMock(PreUpdateEventArgs::class);
        $settings = new StripeTransportSettings();

        $event->expects($this->once())
            ->method('hasChangedField')
            ->with('userMonitoring')
            ->willReturn(true);

        $this->cache->expects($this->once())
            ->method('deleteItem');

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
}
