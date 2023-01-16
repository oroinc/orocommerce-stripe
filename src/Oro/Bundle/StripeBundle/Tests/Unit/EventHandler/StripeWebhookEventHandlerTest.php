<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventHandler;

use Oro\Bundle\StripeBundle\Event\StripeEventFactoryInterface;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\StripeEventHandlerInterface;
use Oro\Bundle\StripeBundle\EventHandler\StripeEventHandlerRegistry;
use Oro\Bundle\StripeBundle\EventHandler\StripeWebhookEventHandler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class StripeWebhookEventHandlerTest extends TestCase
{
    private StripeEventFactoryInterface|MockObject $eventFactoryMock;
    private StripeEventHandlerRegistry|MockObject $handlerRegistryMock;

    private StripeWebhookEventHandler $eventHandler;

    protected function setUp(): void
    {
        $this->eventFactoryMock = $this->createMock(StripeEventFactoryInterface::class);
        $this->handlerRegistryMock = $this->createMock(StripeEventHandlerRegistry::class);

        $this->eventHandler = new StripeWebhookEventHandler(
            $this->eventFactoryMock,
            $this->handlerRegistryMock
        );
    }

    public function testHandleEventSuccess()
    {
        $event = $this->createMock(StripeEventInterface::class);

        $this->eventFactoryMock->expects($this->once())
            ->method('createEventFromRequest')
            ->willReturn($event);

        $handlerMock = $this->createMock(StripeEventHandlerInterface::class);

        $this->handlerRegistryMock->expects($this->once())
            ->method('getHandler')
            ->willReturn($handlerMock);

        $handlerMock->expects($this->once())
            ->method('handle');

        $this->eventHandler->handleEvent(new Request());
    }

    public function testHandleEventWithFailedEventObjectCreation()
    {
        $this->expectException(\LogicException::class);

        $this->eventFactoryMock->expects($this->once())
            ->method('createEventFromRequest')
            ->willThrowException(new \LogicException());

        $this->handlerRegistryMock->expects($this->never())
            ->method('getHandler');

        $this->eventHandler->handleEvent(new Request());
    }
}
