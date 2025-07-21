<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEvent;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\StripeWebhookEvent;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;

final class StripeWebhookEventTest extends TestCase
{
    private StripeWebhookEvent $webhookEvent;

    private StripeEvent $stripeEvent;

    protected function setUp(): void
    {
        $this->stripeEvent = new StripeEvent('evt_123');
        $this->webhookEvent = new StripeWebhookEvent($this->stripeEvent);
    }

    public function testGetEventName(): void
    {
        self::assertSame('oro_payment.callback.stripe_webhook', $this->webhookEvent->getEventName());
    }

    public function testGetStripeEvent(): void
    {
        self::assertSame($this->stripeEvent, $this->webhookEvent->getStripeEvent());
    }

    public function testConstructorWithData(): void
    {
        $data = ['payment_transaction' => 'trx_123'];
        $event = new StripeWebhookEvent($this->stripeEvent, $data);

        self::assertSame($data, $event->getData());
    }

    public function testConstructorWithoutData(): void
    {
        self::assertSame([], $this->webhookEvent->getData());
    }
}
