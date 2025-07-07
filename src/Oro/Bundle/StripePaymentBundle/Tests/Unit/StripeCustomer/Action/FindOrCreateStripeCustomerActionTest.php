<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeCustomer\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FindOrCreateStripeCustomerActionTest extends TestCase
{
    private FindOrCreateStripeCustomerAction $action;

    private MockObject&StripeClientConfigInterface $stripeClientConfig;

    private MockObject&PaymentTransaction $paymentTransaction;

    protected function setUp(): void
    {
        $this->stripeClientConfig = $this->createMock(StripeClientConfigInterface::class);
        $this->paymentTransaction = $this->createMock(PaymentTransaction::class);

        $this->action = new FindOrCreateStripeCustomerAction($this->stripeClientConfig, $this->paymentTransaction);
    }

    public function testGetActionName(): void
    {
        self::assertSame('customer_find_or_create', $this->action->getActionName());
    }

    public function testGetStripeClientConfig(): void
    {
        self::assertSame($this->stripeClientConfig, $this->action->getStripeClientConfig());
    }

    public function testGetPaymentTransaction(): void
    {
        self::assertSame($this->paymentTransaction, $this->action->getPaymentTransaction());
    }
}
