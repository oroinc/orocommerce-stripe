<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Event;

use Oro\Bundle\StripeBundle\Event\StripeSDKEventFactory;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\UnsupportedResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event;
use Stripe\PaymentIntent;
use Symfony\Component\HttpFoundation\Request;

class StripeSDKEventFactoryTest extends TestCase
{
    private MockObject|StripePaymentConfigsProvider $paymentConfigsProvider;
    private MockObject|StripeSDKEventFactory $eventFactory;

    protected function setUp(): void
    {
        $this->paymentConfigsProvider = $this->createMock(StripePaymentConfigsProvider::class);
        $this->eventFactory = $this->getMockBuilder(StripeSDKEventFactory::class)
            ->setConstructorArgs([$this->paymentConfigsProvider])
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->disallowMockingUnknownTypes()
            ->onlyMethods(['constructEvent'])
            ->getMock();
    }

    public function testInvalidStripeEventException()
    {
        $request = Request::createFromGlobals();
        $config = $this->createMock(StripePaymentConfig::class);
        $this->paymentConfigsProvider->expects(self::once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->eventFactory->expects(self::once())
            ->method('constructEvent')
            ->with($request, $config)
            ->willReturn(null);

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('There are no any configured Stripe payment methods available to handle event');

        $this->eventFactory->createEventFromRequest($request);
    }

    public function testNoStripePaymentConfigsException()
    {
        $request = Request::createFromGlobals();
        $this->paymentConfigsProvider->expects(self::once())
            ->method('getConfigs')
            ->willReturn([]);

        $this->eventFactory->expects(self::never())
            ->method('constructEvent');

        self::expectException(\LogicException::class);
        self::expectExceptionMessage('There are no any configured Stripe payment methods available to handle event');

        $this->eventFactory->createEventFromRequest($request);
    }

    public function testSupportedEvent()
    {
        $request = Request::createFromGlobals();
        $config = $this->createMock(StripePaymentConfig::class);
        $this->paymentConfigsProvider->expects(self::once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $stripeEventValues = [
            'data' => (object) ['object' => new PaymentIntent()],
            'type' => 'customer.created',
        ];
        $stripeEvent = $this->createMock(Event::class);
        $stripeEvent->expects(self::exactly(2))
            ->method('__get')
            ->willReturnCallback(function ($name) use ($stripeEventValues) {
                return $stripeEventValues[$name];
            });

        $this->eventFactory->expects(self::once())
            ->method('constructEvent')
            ->with($request, $config)
            ->willReturn($stripeEvent);

        $event = $this->eventFactory->createEventFromRequest($request);

        self::assertInstanceOf(PaymentIntentResponse::class, $event->getData());
    }
    public function testUnsupportedEvent()
    {
        $request = Request::createFromGlobals();
        $config = $this->createMock(StripePaymentConfig::class);
        $this->paymentConfigsProvider->expects(self::once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $stripeEventValues = [
            'data' => (object) ['object' => new \stdClass()],
            'type' => 'customer.created',
        ];
        $stripeEvent = $this->createMock(Event::class);
        $stripeEvent->expects(self::exactly(2))
            ->method('__get')
            ->willReturnCallback(function ($name) use ($stripeEventValues) {
                return $stripeEventValues[$name];
            });

        $this->eventFactory->expects(self::once())
            ->method('constructEvent')
            ->with($request, $config)
            ->willReturn($stripeEvent);

        $event = $this->eventFactory->createEventFromRequest($request);

        self::assertInstanceOf(UnsupportedResponse::class, $event->getData());
    }
}
