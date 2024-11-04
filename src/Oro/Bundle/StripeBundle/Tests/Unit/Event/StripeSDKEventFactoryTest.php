<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Event;

use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
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
    private MockObject|ManagerRegistry $managerRegistry;
    private MockObject|StripeSDKEventFactory $eventFactory;

    #[\Override]
    protected function setUp(): void
    {
        $this->paymentConfigsProvider = $this->createMock(StripePaymentConfigsProvider::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->eventFactory = $this->getMockBuilder(StripeSDKEventFactory::class)
            ->setConstructorArgs([
                $this->paymentConfigsProvider,
                $this->managerRegistry
            ])
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
        $config1 = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_1',
        ]);
        $config2 = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_2',
        ]);

        $this->paymentConfigsProvider->expects(self::once())
            ->method('getConfigs')
            ->willReturn([$config1, $config2]);

        $stripeEventValues = [
            'data' => (object) ['object' => new PaymentIntent('pi_customer.created')],
            'type' => 'customer.created',
        ];
        $stripeEvent = $this->createMock(Event::class);
        $stripeEvent->expects(self::exactly(3))
            ->method('__get')
            ->willReturnCallback(function ($name) use ($stripeEventValues) {
                return $stripeEventValues[$name];
            });

        $this->eventFactory->expects(self::exactly(2))
            ->method('constructEvent')
            ->withConsecutive([$request, $config1], [$request, $config2])
            ->willReturnOnConsecutiveCalls($stripeEvent, $stripeEvent);

        $repository = $this->createMock(EntityRepository::class);

        $this->managerRegistry->expects($this->once())
            ->method('getRepository')
            ->with(PaymentTransaction::class)
            ->willReturn($repository);

        $paymentTransaction = (new PaymentTransaction())->setPaymentMethod('stripe_payment_2');

        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['reference' => 'pi_customer.created'])
            ->willReturn($paymentTransaction);

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
