<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\OrderBundle\Entity\Repository\OrderRepository;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Provider\SubOrdersByPaymentTransactionProvider;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubOrdersByPaymentTransactionProviderTest extends TestCase
{
    private const string PAYMENT_METHOD = 'stripe_payment_element';

    private SubOrdersByPaymentTransactionProvider $provider;

    private MockObject&OrderRepository $orderRepository;

    #[\Override]
    protected function setUp(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);

        $doctrine
            ->method('getRepository')
            ->with(Order::class)
            ->willReturn($this->orderRepository);

        $this->provider = new SubOrdersByPaymentTransactionProvider($doctrine);
    }

    private function createOrder(int $id, float $total = 300.00, string $currency = 'USD'): Order
    {
        $order = new Order();
        ReflectionUtil::setId($order, $id);
        $order->setTotal($total);
        $order->setCurrency($currency);

        return $order;
    }

    private function createPaymentTransaction(
        Order $order,
        string $entityClass = Order::class
    ): PaymentTransaction {
        $transaction = new PaymentTransaction();
        ReflectionUtil::setId($transaction, 100);
        $transaction->setPaymentMethod(self::PAYMENT_METHOD);
        $transaction->setEntityClass($entityClass);
        $transaction->setEntityIdentifier($order->getId());
        $transaction->setAmount((string)$order->getTotal());
        $transaction->setCurrency($order->getCurrency());

        return $transaction;
    }

    private function createSubOrder(int $id, float $total, string $currency = 'USD'): Order
    {
        return $this->createOrder($id, $total, $currency);
    }

    public function testHasSubOrdersReturnsTrueWhenSubOrdersExist(): void
    {
        $order = $this->createOrder(1);
        $paymentTransaction = $this->createPaymentTransaction($order);

        $this->orderRepository
            ->expects(self::once())
            ->method('hasSubOrders')
            ->with(1)
            ->willReturn(true);

        $result = $this->provider->hasSubOrders($paymentTransaction);

        self::assertTrue($result);
    }

    public function testHasSubOrdersReturnsFalseWhenNoSubOrdersExist(): void
    {
        $order = $this->createOrder(1);
        $paymentTransaction = $this->createPaymentTransaction($order);

        $this->orderRepository
            ->expects(self::once())
            ->method('hasSubOrders')
            ->with(1)
            ->willReturn(false);

        $result = $this->provider->hasSubOrders($paymentTransaction);

        self::assertFalse($result);
    }

    public function testHasSubOrdersReturnsFalseWhenEntityClassIsNotOrder(): void
    {
        $order = $this->createOrder(1);
        $paymentTransaction = $this->createPaymentTransaction($order, 'SomeOtherClass');

        $this->orderRepository
            ->expects(self::never())
            ->method('hasSubOrders');

        $result = $this->provider->hasSubOrders($paymentTransaction);

        self::assertFalse($result);
    }

    public function testGetSubOrdersReturnsSubOrders(): void
    {
        $order = $this->createOrder(1, 300.00);
        $paymentTransaction = $this->createPaymentTransaction($order);

        $subOrder1 = $this->createSubOrder(101, 100.00);
        $subOrder2 = $this->createSubOrder(102, 150.00);
        $subOrder3 = $this->createSubOrder(103, 50.00);

        $this->orderRepository
            ->expects(self::once())
            ->method('findSubOrders')
            ->with(1)
            ->willReturn([$subOrder1, $subOrder2, $subOrder3]);

        $result = $this->provider->getSubOrders($paymentTransaction);

        self::assertCount(3, $result);
        self::assertSame($subOrder1, $result[0]);
        self::assertSame($subOrder2, $result[1]);
        self::assertSame($subOrder3, $result[2]);
    }

    public function testGetSubOrdersReturnsEmptyArrayWhenNoSubOrders(): void
    {
        $order = $this->createOrder(1);
        $paymentTransaction = $this->createPaymentTransaction($order);

        $this->orderRepository
            ->expects(self::once())
            ->method('findSubOrders')
            ->with(1)
            ->willReturn([]);

        $result = $this->provider->getSubOrders($paymentTransaction);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testGetSubOrdersReturnsEmptyArrayWhenEntityClassIsNotOrder(): void
    {
        $order = $this->createOrder(1);
        $paymentTransaction = $this->createPaymentTransaction($order, 'SomeOtherClass');

        $this->orderRepository
            ->expects(self::never())
            ->method('findSubOrders');

        $result = $this->provider->getSubOrders($paymentTransaction);

        self::assertIsArray($result);
        self::assertEmpty($result);
    }
}
