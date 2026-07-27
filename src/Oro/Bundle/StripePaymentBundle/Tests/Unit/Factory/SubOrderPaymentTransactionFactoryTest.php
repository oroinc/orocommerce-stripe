<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Factory;

use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\Factory\SubOrderPaymentTransactionFactory;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SubOrderPaymentTransactionFactoryTest extends TestCase
{
    private const string PAYMENT_METHOD = 'stripe_payment_element';
    private const string PARENT_ACCESS_IDENTIFIER = 'parent_access_identifier';
    private const string PARENT_ACCESS_TOKEN = 'parent_access_token';

    private SubOrderPaymentTransactionFactory $factory;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    #[\Override]
    protected function setUp(): void
    {
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->factory = new SubOrderPaymentTransactionFactory($this->paymentTransactionProvider);
    }

    public function testCreateSubOrderPaymentTransaction(): void
    {
        $parentOrder = new Order();
        ReflectionUtil::setId($parentOrder, 1);
        $parentOrder->setTotal(300.00);
        $parentOrder->setCurrency('USD');

        $parentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($parentTransaction, 100);
        $parentTransaction->setAction(PaymentMethodInterface::PURCHASE);
        $parentTransaction->setAccessIdentifier(self::PARENT_ACCESS_IDENTIFIER);
        $parentTransaction->setAccessToken(self::PARENT_ACCESS_TOKEN);
        $parentTransaction->setAmount((string)$parentOrder->getTotal());
        $parentTransaction->setCurrency($parentOrder->getCurrency());
        $parentTransaction->setPaymentMethod(self::PAYMENT_METHOD);
        $parentTransaction->setEntityClass(Order::class);
        $parentTransaction->setEntityIdentifier($parentOrder->getId());
        $parentTransaction->addTransactionOption('additionalData', ['key1' => 'value1']);
        $parentTransaction->addTransactionOption('option2', 'value2');

        $subOrder = new Order();
        ReflectionUtil::setId($subOrder, 101);
        $subOrder->setTotal(150.00);
        $subOrder->setCurrency('USD');

        $subPaymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($subPaymentTransaction, 201);
        $subPaymentTransaction->setAction($parentTransaction->getAction());
        $subPaymentTransaction->setPaymentMethod(self::PAYMENT_METHOD);
        $subPaymentTransaction->setEntityClass(Order::class);
        $subPaymentTransaction->setEntityIdentifier($subOrder->getId());

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('createPaymentTransaction')
            ->with(self::PAYMENT_METHOD, PaymentMethodInterface::PURCHASE, $subOrder)
            ->willReturn($subPaymentTransaction);

        $result = $this->factory->createSubOrderPaymentTransaction($parentTransaction, $subOrder);

        self::assertSame($subPaymentTransaction, $result);
        self::assertEquals('150', $result->getAmount());
        self::assertEquals('USD', $result->getCurrency());
        self::assertSame($parentTransaction, $result->getSourcePaymentTransaction());
        self::assertEquals(
            $parentTransaction->getTransactionOptions(),
            $result->getTransactionOptions()
        );
        self::assertEquals(['key1' => 'value1'], $result->getTransactionOption('additionalData'));
        self::assertEquals('value2', $result->getTransactionOption('option2'));
    }
}
