<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\EventListener\Doctrine\DeleteStripePaymentElementScriptCacheDoctrineListener;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripePaymentElementStripeScriptProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class DeleteStripePaymentElementScriptCacheDoctrineListenerTest extends TestCase
{
    private CacheInterface&MockObject $cache;
    private DeleteStripePaymentElementScriptCacheDoctrineListener $listener;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->listener = new DeleteStripePaymentElementScriptCacheDoctrineListener($this->cache);
    }

    public function testOnFlushWithChannelInsertion(): void
    {
        $organization = new Organization();
        $organization->setId(123);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(123)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(123)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithSettingsInsertion(): void
    {
        $organization = new Organization();
        $organization->setId(456);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setEnabled(true);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);
        $settings->setChannel($channel);
        $channel->setTransport($settings);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$settings]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(456)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(456)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithChannelInsertionDisabledChannel(): void
    {
        $organization = new Organization();
        $organization->setId(789);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(false);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithChannelUpdate(): void
    {
        $organization = new Organization();
        $organization->setId(321);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$channel]);
        $unitOfWork
            ->expects(self::exactly(2))
            ->method('getEntityChangeSet')
            ->willReturnMap([
                [$channel, ['enabled' => [false, true]]],
                [$settings, []],
            ]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(321)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(321)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithSettingsUpdate(): void
    {
        $organization = new Organization();
        $organization->setId(654);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setEnabled(true);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);
        $settings->setChannel($channel);
        $channel->setTransport($settings);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$settings]);
        $unitOfWork
            ->expects(self::exactly(2))
            ->method('getEntityChangeSet')
            ->willReturnMap([
                [$channel, []],
                [$settings, ['userMonitoringEnabled' => [false, true]]],
            ]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(654)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(654)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithSettingsDeletion(): void
    {
        $organization = new Organization();
        $organization->setId(987);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setEnabled(true);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);
        $settings->setChannel($channel);
        $channel->setTransport($settings);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$settings]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(987)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(987)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithSettingsDeletionUserMonitoringDisabled(): void
    {
        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(false);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$settings]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithMultipleOrganizations(): void
    {
        $organization1 = new Organization();
        $organization1->setId(111);
        $organization2 = new Organization();
        $organization2->setId(222);

        $settings1 = new StripePaymentElementSettings();
        $settings1->setUserMonitoringEnabled(true);
        $channel1 = new Channel();
        $channel1->setOrganization($organization1);
        $channel1->setTransport($settings1);
        $settings1->setChannel($channel1);
        $channel1->setEnabled(true);

        $settings2 = new StripePaymentElementSettings();
        $settings2->setUserMonitoringEnabled(true);
        $channel2 = new Channel();
        $channel2->setOrganization($organization2);
        $channel2->setTransport($settings2);
        $settings2->setChannel($channel2);
        $channel2->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel1, $channel2]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(4))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(111)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(111)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(222)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(222)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnFlushWithNullOrganization(): void
    {
        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(0)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(0)],
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testPostFlushResetsAffectedOrganizations(): void
    {
        $organization = new Organization();
        $organization->setId(123);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel]);
        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        // First flush
        $this->listener->onFlush($eventArgs);

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(123)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(123)]
            );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);

        // Second flush should not delete cache as organizations were reset
        $this->cache
            ->expects(self::never())
            ->method('delete');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testOnClear(): void
    {
        $organization = new Organization();
        $organization->setId(123);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel]);
        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);
        $this->listener->onClear();

        // After onClear, postFlush should not delete any cache
        $this->cache
            ->expects(self::never())
            ->method('delete');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }

    public function testReset(): void
    {
        $organization = new Organization();
        $organization->setId(123);

        $settings = new StripePaymentElementSettings();
        $settings->setUserMonitoringEnabled(true);

        $channel = new Channel();
        $channel->setOrganization($organization);
        $channel->setTransport($settings);
        $settings->setChannel($channel);
        $channel->setEnabled(true);

        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork
            ->method('getScheduledEntityInsertions')
            ->willReturn([$channel]);
        $unitOfWork
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);
        $unitOfWork
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);
        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);
        $this->listener->reset();

        // After reset, postFlush should not delete any cache
        $this->cache
            ->expects(self::never())
            ->method('delete');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $postFlushArgs = new PostFlushEventArgs($entityManager);
        $this->listener->postFlush($postFlushArgs);
    }
}
