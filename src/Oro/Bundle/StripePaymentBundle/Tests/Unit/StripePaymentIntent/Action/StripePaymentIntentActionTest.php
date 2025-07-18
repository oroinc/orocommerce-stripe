<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripePaymentIntentActionTest extends TestCase
{
    private const string SAMPLE_ACTION_NAME = 'sample_action';

    private StripePaymentIntentAction $action;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private PaymentTransaction $paymentTransaction;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->paymentTransaction = new PaymentTransaction();

        $this->action = new StripePaymentIntentAction(
            self::SAMPLE_ACTION_NAME,
            $this->stripePaymentElementConfig,
            $this->paymentTransaction
        );

        $this->stripePaymentElementConfig
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element');
    }

    public function testGetActionName(): void
    {
        self::assertSame(self::SAMPLE_ACTION_NAME, $this->action->getActionName());
    }

    public function testGetPaymentTransaction(): void
    {
        self::assertSame($this->paymentTransaction, $this->action->getPaymentTransaction());
    }

    public function testGetStripeClientConfig(): void
    {
        self::assertSame($this->stripePaymentElementConfig, $this->action->getStripeClientConfig());
    }

    public function testGetPaymentIntentConfig(): void
    {
        self::assertSame($this->stripePaymentElementConfig, $this->action->getPaymentIntentConfig());
    }
}
