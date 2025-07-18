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

final class DeleteStripePaymentElementScriptCacheDoctrineListenerTest extends TestCase
{
    private DeleteStripePaymentElementScriptCacheDoctrineListener $listener;

    private CacheInterface&MockObject $cache;

    private UnitOfWork&MockObject $unitOfWork;

    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);

        $this->listener = new DeleteStripePaymentElementScriptCacheDoctrineListener($this->cache);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $this->entityManager
            ->method('getUnitOfWork')
            ->willReturn($this->unitOfWork);
    }

    public function testOnFlushWhenInsertedWithUserMonitoring(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(true);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        // Verify cache deletion happens in postFlush
        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(42)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(42)]
            );

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnFlushWhenInsertedWithoutUserMonitoring(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(false);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnFlushWhenUpdatedWithUserMonitoring(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())->setChannel($channel);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getEntityChangeSet')
            ->with($settings)
            ->willReturn(['userMonitoringEnabled' => [false, true]]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(42)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(42)]
            );

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnFlushWhenUpdatedWithoutUserMonitoring(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())->setChannel($channel);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getEntityChangeSet')
            ->with($settings)
            ->willReturn(['reAuthorizationEnabled' => [false, true]]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnFlushWhenDeletedWithUserMonitoring(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(true);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$settings]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->cache
            ->expects(self::exactly(2))
            ->method('delete')
            ->withConsecutive(
                [StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(42)],
                [StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(42)]
            );

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnFlushWhenDeletedWithoutUserMonitoring(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(false);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$settings]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnFlushWithNonRelevantEntities(): void
    {
        $otherEntity = new \stdClass();

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$otherEntity]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([$otherEntity]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$otherEntity]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        // No cache deletion should happen
        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testPostFlushWithException(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(true);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $exception = new \RuntimeException('Cache error');
        $this->cache
            ->expects(self::once())
            ->method('delete')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testOnClear(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(true);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->listener->onClear();

        // After onClear, postFlush should not delete anything
        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }

    public function testReset(): void
    {
        $organization = (new Organization())->setId(42);
        $channel = (new Channel())->setOrganization($organization);
        $settings = (new StripePaymentElementSettings())
            ->setChannel($channel)
            ->setUserMonitoringEnabled(true);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityInsertions')
            ->willReturn([$settings]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityUpdates')
            ->willReturn([]);

        $this->unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([]);

        $this->listener->onFlush(new OnFlushEventArgs($this->entityManager));

        $this->listener->reset();

        // After reset, postFlush should not delete anything
        $this->cache
            ->expects(self::never())
            ->method('delete');

        $this->listener->postFlush(new PostFlushEventArgs($this->entityManager));
    }
}
