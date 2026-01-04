<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventListener;

use Oro\Bundle\ActionBundle\Event\OperationAnnounceEvent;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\OperationDefinition;
use Oro\Bundle\StripeBundle\EventListener\PaymentTransactionOperationAnnounceEventListener;
use PHPUnit\Framework\TestCase;

class PaymentTransactionOperationAnnounceEventListenerTest extends TestCase
{
    /** @dataProvider onOperationAnnounceDataProvider */
    public function testOnOperationAnnounce(string $paymentPrefix, string $paymentMethod, bool $isAllowed): void
    {
        $listener = new PaymentTransactionOperationAnnounceEventListener($paymentPrefix);
        $event = $this->getOperationAnnounceEvent($paymentMethod);

        $listener->onOperationAnnounce($event);

        self::assertEquals($isAllowed, $event->isAllowed());
    }

    public function onOperationAnnounceDataProvider(): array
    {
        return [
            [
                'paymentPrefix' => 'test_payment',
                'paymentMethod' => 'stripe_payment_1',
                'isAllowed' => true,
            ],
            [
                'paymentPrefix' => 'stripe_payment',
                'paymentMethod' => 'stripe_payment_1',
                'isAllowed' => false,
            ],
        ];
    }

    private function getOperationAnnounceEvent(string $paymentMethod): OperationAnnounceEvent
    {
        $entityClass = $this->getEntityClass($paymentMethod);
        $actionDataMock = $this->createMock(ActionData::class);
        $operationDefinitionMock = $this->createMock(OperationDefinition::class);
        $actionDataMock->method('getEntity')->willReturn($entityClass);

        return new OperationAnnounceEvent($actionDataMock, $operationDefinitionMock);
    }

    private function getEntityClass(string $paymentMethod): object
    {
        return new class ($paymentMethod) {
            public function __construct(
                private string $paymentMethod
            ) {
            }
            public function getPaymentMethod(): string
            {
                return $this->paymentMethod;
            }
        };
    }
}
