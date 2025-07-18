<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEvent\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider\PaymentTransactionByStripeEventProvider;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Provider\PaymentTransactionByStripeEventProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Event as StripeEvent;

final class PaymentTransactionByStripeEventProviderTest extends TestCase
{
    private PaymentTransactionByStripeEventProvider $provider;

    private MockObject&PaymentTransactionByStripeEventProviderInterface $firstProvider;

    private MockObject&PaymentTransactionByStripeEventProviderInterface $secondProvider;

    private StripeEvent $stripeEvent;

    protected function setUp(): void
    {
        $this->firstProvider = $this->createMock(PaymentTransactionByStripeEventProviderInterface::class);
        $this->secondProvider = $this->createMock(PaymentTransactionByStripeEventProviderInterface::class);
        $this->stripeEvent = new StripeEvent();

        $this->provider = new PaymentTransactionByStripeEventProvider([
            $this->firstProvider,
            $this->secondProvider,
        ]);
    }

    public function testIsApplicableWhenNoProvidersMatch(): void
    {
        $this->firstProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(false);

        $this->secondProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(false);

        self::assertFalse($this->provider->isApplicable($this->stripeEvent));
    }

    public function testIsApplicableWhenFirstProviderMatches(): void
    {
        $this->firstProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(true);

        // Second provider should not be called
        $this->secondProvider
            ->expects(self::never())
            ->method('isApplicable');

        self::assertTrue($this->provider->isApplicable($this->stripeEvent));
    }

    public function testIsApplicableWhenSecondProviderMatches(): void
    {
        $this->firstProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(false);

        $this->secondProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(true);

        self::assertTrue($this->provider->isApplicable($this->stripeEvent));
    }

    public function testFindPaymentTransactionWhenNoProvidersMatch(): void
    {
        $this->firstProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(false);

        $this->secondProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(false);

        self::assertNull($this->provider->findPaymentTransactionByStripeEvent($this->stripeEvent));
    }

    public function testFindPaymentTransactionWhenFirstProviderMatches(): void
    {
        $transaction = new PaymentTransaction();

        $this->firstProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(true);

        $this->firstProvider
            ->expects(self::once())
            ->method('findPaymentTransactionByStripeEvent')
            ->with($this->stripeEvent)
            ->willReturn($transaction);

        // Second provider should not be called
        $this->secondProvider
            ->expects(self::never())
            ->method('isApplicable');
        $this->secondProvider
            ->expects(self::never())
            ->method('findPaymentTransactionByStripeEvent');

        self::assertSame($transaction, $this->provider->findPaymentTransactionByStripeEvent($this->stripeEvent));
    }

    public function testFindPaymentTransactionWhenSecondProviderMatches(): void
    {
        $transaction = new PaymentTransaction();

        $this->firstProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(false);

        $this->secondProvider
            ->expects(self::once())
            ->method('isApplicable')
            ->with($this->stripeEvent)
            ->willReturn(true);

        $this->secondProvider
            ->expects(self::once())
            ->method('findPaymentTransactionByStripeEvent')
            ->with($this->stripeEvent)
            ->willReturn($transaction);

        self::assertSame($transaction, $this->provider->findPaymentTransactionByStripeEvent($this->stripeEvent));
    }

    public function testFindPaymentTransactionWithEmptyProviders(): void
    {
        $provider = new PaymentTransactionByStripeEventProvider([]);

        self::assertFalse($provider->isApplicable($this->stripeEvent));
        self::assertNull($provider->findPaymentTransactionByStripeEvent($this->stripeEvent));
    }
}
