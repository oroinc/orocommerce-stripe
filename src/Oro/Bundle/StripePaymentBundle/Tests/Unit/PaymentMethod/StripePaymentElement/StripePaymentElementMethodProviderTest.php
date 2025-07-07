<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement;

use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethodFactory;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\StripePaymentElementMethodProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripePaymentElementMethodProviderTest extends TestCase
{
    private StripePaymentElementMethodProvider $provider;

    private MockObject&StripePaymentElementConfigProvider $stripePaymentElementConfigProvider;

    private MockObject&StripePaymentElementMethodFactory $stripePaymentElementPaymentMethodFactory;

    private array $paymentMethodGroups = ['sample_group1', 'sample_group2'];

    protected function setUp(): void
    {
        $this->stripePaymentElementConfigProvider = $this->createMock(StripePaymentElementConfigProvider::class);
        $this->stripePaymentElementPaymentMethodFactory = $this->createMock(
            StripePaymentElementMethodFactory::class
        );

        $this->provider = new StripePaymentElementMethodProvider(
            $this->stripePaymentElementConfigProvider,
            $this->stripePaymentElementPaymentMethodFactory,
            $this->paymentMethodGroups
        );
    }

    public function testCollectMethodsWithNoConfigs(): void
    {
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([]);

        self::assertEmpty($this->provider->getPaymentMethods());
    }

    public function testCollectMethodsWithSingleConfig(): void
    {
        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        $this->stripePaymentElementPaymentMethodFactory
            ->expects(self::once())
            ->method('create')
            ->with($stripePaymentElementConfig, $this->paymentMethodGroups)
            ->willReturn($paymentMethod);

        $paymentMethods = $this->provider->getPaymentMethods();

        self::assertCount(1, $paymentMethods);
        self::assertSame($paymentMethod, $paymentMethods['stripe_payment_element_11']);
    }

    public function testCollectMethodsWithMultipleConfigs(): void
    {
        $stripePaymentElementConfig1 = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig2 = $this->createMock(StripePaymentElementConfig::class);

        $paymentMethod1 = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod1
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('stripe_payment_element_11');

        $paymentMethod2 = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod2
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('stripe_payment_element_22');

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([
                'stripe_payment_element_11' => $stripePaymentElementConfig1,
                'stripe_payment_element_22' => $stripePaymentElementConfig2,
            ]);

        $this->stripePaymentElementPaymentMethodFactory
            ->expects(self::exactly(2))
            ->method('create')
            ->withConsecutive(
                [$stripePaymentElementConfig1, $this->paymentMethodGroups],
                [$stripePaymentElementConfig2, $this->paymentMethodGroups]
            )
            ->willReturnOnConsecutiveCalls($paymentMethod1, $paymentMethod2);

        $paymentMethods = $this->provider->getPaymentMethods();

        self::assertCount(2, $paymentMethods);
        self::assertSame($paymentMethod1, $paymentMethods['stripe_payment_element_11']);
        self::assertSame($paymentMethod2, $paymentMethods['stripe_payment_element_22']);
    }

    public function testMethodsAreCollectedOnlyOnce(): void
    {
        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        $this->stripePaymentElementPaymentMethodFactory
            ->expects(self::once())
            ->method('create')
            ->with($stripePaymentElementConfig, $this->paymentMethodGroups)
            ->willReturn($paymentMethod);

        // Multiple calls should only collect methods once
        $this->provider->getPaymentMethods();
        $this->provider->hasPaymentMethod('stripe_payment_element_11');
        $this->provider->getPaymentMethod('stripe_payment_element_11');
    }

    public function testHasPaymentMethod(): void
    {
        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        $this->stripePaymentElementPaymentMethodFactory
            ->expects(self::once())
            ->method('create')
            ->with($stripePaymentElementConfig, $this->paymentMethodGroups)
            ->willReturn($paymentMethod);

        self::assertTrue($this->provider->hasPaymentMethod('stripe_payment_element_11'));
        self::assertFalse($this->provider->hasPaymentMethod('nonexistent'));
    }

    public function testGetPaymentMethod(): void
    {
        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod
            ->expects(self::once())
            ->method('getIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        $this->stripePaymentElementPaymentMethodFactory
            ->expects(self::once())
            ->method('create')
            ->with($stripePaymentElementConfig, $this->paymentMethodGroups)
            ->willReturn($paymentMethod);

        self::assertSame($paymentMethod, $this->provider->getPaymentMethod('stripe_payment_element_11'));
        self::assertNull($this->provider->getPaymentMethod('nonexistent'));
    }
}
