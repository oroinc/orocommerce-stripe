<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use LogicException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use PHPUnit\Framework\TestCase;

class PaymentActionRegistryTest extends TestCase
{
    public function testGetPaymentActionExceptionWithEmptyActions(): void
    {
        $transaction = new PaymentTransaction();
        $registry = new PaymentActionRegistry([]);
        $this->expectException(LogicException::class);

        $registry->getPaymentAction('test', $transaction);
    }

    public function testGetPaymentActionExceptionWithNotApplicableAction(): void
    {
        $transaction = new PaymentTransaction();
        $action = $this->createMock(PaymentActionInterface::class);
        $action->expects($this->once())
            ->method('isApplicable')
            ->with('test', $transaction)
            ->willReturn(false);
        $registry = new PaymentActionRegistry([$action]);
        $this->expectException(LogicException::class);

        $registry->getPaymentAction('test', $transaction);
    }

    public function testGetPaymentAction(): void
    {
        $transaction = new PaymentTransaction();
        $action = $this->createMock(PaymentActionInterface::class);
        $action->expects($this->once())
            ->method('isApplicable')
            ->with('test', $transaction)
            ->willReturn(true);
        $registry = new PaymentActionRegistry([$action]);

        $result = $registry->getPaymentAction('test', $transaction);
        $this->assertEquals($action, $result);
    }
}
