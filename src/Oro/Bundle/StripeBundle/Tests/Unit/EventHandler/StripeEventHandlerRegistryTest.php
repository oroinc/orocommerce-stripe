<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventHandler;

use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\EventHandler\Exception\NotSupportedEventException;
use Oro\Bundle\StripeBundle\EventHandler\StripeEventHandlerInterface;
use Oro\Bundle\StripeBundle\EventHandler\StripeEventHandlerRegistry;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Oro\Bundle\StripeBundle\Tests\Unit\Stub\TestEventHandler;
use PHPUnit\Framework\TestCase;

class StripeEventHandlerRegistryTest extends TestCase
{
    private StripeEventHandlerRegistry $handlerRegistry;
    private StripeEventHandlerInterface $handler;

    #[\Override]
    protected function setUp(): void
    {
        $this->handler = new TestEventHandler();
        $handlers = [$this->handler];
        $this->handlerRegistry = new StripeEventHandlerRegistry($handlers);
    }

    public function testGetHandlerSuccess(): void
    {
        $responseObject = $this->createMock(ResponseObjectInterface::class);
        $event = new StripeEvent('test_payment.succeeded', new StripePaymentConfig(), $responseObject, 'stripe_1');

        $this->assertSame($this->handler, $this->handlerRegistry->getHandler($event));
    }

    public function testEventIsNotSupported(): void
    {
        $this->expectException(NotSupportedEventException::class);
        $this->expectExceptionMessage('Event "test_payment.failed" is not supported');

        $responseObject = $this->createMock(ResponseObjectInterface::class);
        $event = new StripeEvent('test_payment.failed', new StripePaymentConfig(), $responseObject, 'stripe_1');

        $this->assertSame($this->handler, $this->handlerRegistry->getHandler($event));
    }
}
