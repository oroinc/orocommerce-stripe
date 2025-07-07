<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Action;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;

final class StripePaymentIntentWebhookActionTest extends TestCase
{
    private StripePaymentIntentWebhookAction $action;

    private StripeEvent $stripeEvent;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private PaymentTransaction $paymentTransaction;

    protected function setUp(): void
    {
        $this->stripeEvent = new StripeEvent('evt_123');
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->paymentTransaction = new PaymentTransaction();

        $this->action = new StripePaymentIntentWebhookAction(
            $this->stripeEvent,
            $this->stripePaymentElementConfig,
            $this->paymentTransaction
        );
    }

    public function testGetActionName(): void
    {
        $this->stripeEvent->type = 'payment_intent.succeeded';

        self::assertSame('webhook:payment_intent.succeeded', $this->action->getActionName());
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

    public function testGetStripeEvent(): void
    {
        self::assertSame($this->stripeEvent, $this->action->getStripeEvent());
    }
}
