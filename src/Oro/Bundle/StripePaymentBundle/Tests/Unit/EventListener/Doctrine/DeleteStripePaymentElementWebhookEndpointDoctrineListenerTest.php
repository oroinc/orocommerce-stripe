<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\EventListener\Doctrine\DeleteStripePaymentElementWebhookEndpointDoctrineListener;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\StripeWebhookEndpointActionExecutorInterface;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

final class DeleteStripePaymentElementWebhookEndpointDoctrineListenerTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private DeleteStripePaymentElementWebhookEndpointDoctrineListener $listener;

    private MockObject&StripePaymentElementConfigFactory $stripePaymentElementConfigFactory;

    private MockObject&StripeWebhookEndpointActionExecutorInterface $stripeWebhookEndpointActionExecutor;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfigFactory = $this->createMock(StripePaymentElementConfigFactory::class);
        $this->stripeWebhookEndpointActionExecutor = $this->createMock(
            StripeWebhookEndpointActionExecutorInterface::class
        );

        $this->listener = new DeleteStripePaymentElementWebhookEndpointDoctrineListener(
            $this->stripePaymentElementConfigFactory,
            $this->stripeWebhookEndpointActionExecutor
        );
        $this->setUpLoggerMock($this->listener);
    }

    public function testImplementsRequiredInterfaces(): void
    {
        self::assertInstanceOf(ResetInterface::class, $this->listener);
    }

    public function testOnFlushWhenNoRelevantEntities(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([new \stdClass()]);

        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }

    public function testOnFlushWithStripePaymentElementSettings(): void
    {
        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$stripePaymentElementSettings]);

        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $eventArgs = new OnFlushEventArgs($entityManager);

        $stripePaymentElementConfig = new StripePaymentElementConfig(['sample_key' => 'value']);

        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripePaymentElementConfig);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::once())
            ->method('executeAction')
            ->with(
                new DeleteStripeWebhookEndpointAction($stripePaymentElementConfig)
            );

        $this->listener->onFlush($eventArgs);

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }

    public function testPostFlushWithMultipleDeletedSettings(): void
    {
        $stripePaymentElementSettings1 = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings1, 42);
        $stripePaymentElementSettings2 = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings2, 43);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$stripePaymentElementSettings1, $stripePaymentElementSettings2]);

        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $eventArgs = new OnFlushEventArgs($entityManager);

        $stripePaymentElementConfig1 = new StripePaymentElementConfig(['sample_key' => 'sample_value1']);
        $stripePaymentElementConfig2 = new StripePaymentElementConfig(['sample_key' => 'sample_value2']);

        $this->stripePaymentElementConfigFactory
            ->expects(self::exactly(2))
            ->method('createConfig')
            ->withConsecutive([$stripePaymentElementSettings1], [$stripePaymentElementSettings2])
            ->willReturnOnConsecutiveCalls($stripePaymentElementConfig1, $stripePaymentElementConfig2);

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::exactly(2))
            ->method('executeAction')
            ->withConsecutive(
                [new DeleteStripeWebhookEndpointAction($stripePaymentElementConfig1)],
                [new DeleteStripeWebhookEndpointAction($stripePaymentElementConfig2)]
            );

        $this->listener->onFlush($eventArgs);

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }

    public function testPostFlushWithNoDeletedSettings(): void
    {
        $this->stripePaymentElementConfigFactory
            ->expects(self::never())
            ->method('createConfig');

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }

    public function testPostFlushHandlesException(): void
    {
        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$stripePaymentElementSettings]);

        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $eventArgs = new OnFlushEventArgs($entityManager);

        $throwable = new \Exception('Sample exception');
        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->willThrowException($throwable);

        $this->listener->onFlush($eventArgs);

        $this->loggerMock
            ->expects(self::once())
            ->method('error')
            ->with(
                'Failed to delete the Stripe Webhook Endpoint for #{transportSettingsId}: {message}',
                [
                    'transportSettingsId' => $stripePaymentElementSettings->getId(),
                    'message' => $throwable->getMessage(),
                    'throwable' => $throwable,
                ]
            );

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }

    public function testResetClearsPendingDeletions(): void
    {
        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$stripePaymentElementSettings]);

        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->listener->reset();

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }

    public function testOnClearClearsPendingDeletions(): void
    {
        $stripePaymentElementSettings = new StripePaymentElementSettings();
        ReflectionUtil::setId($stripePaymentElementSettings, 42);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $unitOfWork = $this->createMock(UnitOfWork::class);

        $unitOfWork
            ->expects(self::once())
            ->method('getScheduledEntityDeletions')
            ->willReturn([$stripePaymentElementSettings]);

        $entityManager
            ->expects(self::once())
            ->method('getUnitOfWork')
            ->willReturn($unitOfWork);

        $eventArgs = new OnFlushEventArgs($entityManager);

        $this->listener->onFlush($eventArgs);

        $this->listener->onClear();

        $this->stripeWebhookEndpointActionExecutor
            ->expects(self::never())
            ->method('executeAction');

        $this->listener->postFlush(new PostFlushEventArgs($this->createMock(EntityManagerInterface::class)));
    }
}
