<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use LogicException;
use Oro\Bundle\StripeBundle\Client\StripeClientFactory;
use Oro\Bundle\StripeBundle\Method\PaymentAction\ConfirmPaymentAction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use PHPUnit\Framework\TestCase;

class PaymentActionRegistryTest extends TestCase
{
    public function testGetPaymentActionExceptionWithEmptyActions(): void
    {
        $registry = new PaymentActionRegistry([]);
        $this->expectException(LogicException::class);

        $registry->getPaymentAction('test');
    }

    public function testGetPaymentActionExceptionWithNotApplicableAction(): void
    {
        $action = new ConfirmPaymentAction(new StripeClientFactory());
        $registry = new PaymentActionRegistry([$action]);
        $this->expectException(LogicException::class);

        $registry->getPaymentAction('test');
    }

    public function testGetPaymentAction(): void
    {
        $action = new ConfirmPaymentAction(new StripeClientFactory());
        $registry = new PaymentActionRegistry([$action]);

        $result = $registry->getPaymentAction(PaymentActionInterface::CONFIRM_ACTION);
        $this->assertEquals($action, $result);
    }
}
