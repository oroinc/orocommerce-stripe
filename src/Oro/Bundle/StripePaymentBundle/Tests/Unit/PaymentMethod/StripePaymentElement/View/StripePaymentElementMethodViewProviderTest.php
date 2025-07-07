<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement\View;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodView;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodViewProvider;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptEnabledProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class StripePaymentElementMethodViewProviderTest extends TestCase
{
    private StripePaymentElementMethodViewProvider $provider;

    private MockObject&StripeScriptEnabledProvider $scriptEnabledProvider;

    private MockObject&StripePaymentElementConfigProvider $stripePaymentElementConfigProvider;

    private MockObject&StripeAmountConverterInterface $stripeAmountConverter;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfigProvider = $this->createMock(StripePaymentElementConfigProvider::class);
        $this->scriptEnabledProvider = $this->createMock(StripeScriptEnabledProvider::class);
        $this->stripeAmountConverter = $this->createMock(StripeAmountConverterInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->provider = new StripePaymentElementMethodViewProvider(
            $this->stripePaymentElementConfigProvider,
            $this->scriptEnabledProvider,
            $this->stripeAmountConverter,
            $this->eventDispatcher
        );
    }

    public function testHasPaymentMethodViewWhenNoConfigs(): void
    {
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([]);

        self::assertFalse($this->provider->hasPaymentMethodView('any_method'));
    }

    public function testHasPaymentMethodViewWhenConfigExists(): void
    {
        $stripePaymentElementConfig = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_element_11',
        ]);
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        self::assertTrue($this->provider->hasPaymentMethodView('stripe_payment_element_11'));
        self::assertFalse($this->provider->hasPaymentMethodView('nonexistent'));
    }

    public function testGetPaymentMethodViewReturnsNullWhenNotExists(): void
    {
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([]);

        self::assertNull($this->provider->getPaymentMethodView('nonexistent'));
    }

    public function testGetPaymentMethodViewReturnsViewWhenExists(): void
    {
        $stripePaymentElementConfig = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_element_11',
        ]);
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        $expectedPaymentMethodView = new StripePaymentElementMethodView(
            $stripePaymentElementConfig,
            $this->scriptEnabledProvider,
            $this->stripeAmountConverter,
            $this->eventDispatcher
        );
        $paymentMethodView = $this->provider->getPaymentMethodView('stripe_payment_element_11');

        self::assertEquals($expectedPaymentMethodView, $paymentMethodView);
        self::assertSame('stripe_payment_element_11', $paymentMethodView->getPaymentMethodIdentifier());
    }

    public function testGetPaymentMethodViewsReturnsEmptyArrayWhenNoMatches(): void
    {
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([]);

        self::assertSame([], $this->provider->getPaymentMethodViews(['nonexistent']));
    }

    public function testGetPaymentMethodViewsReturnsCorrectViews(): void
    {
        $stripePaymentElementConfig1 = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_element_11',
        ]);
        $stripePaymentElementConfig2 = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_element_22',
        ]);

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([
                'stripe_payment_element_11' => $stripePaymentElementConfig1,
                'stripe_payment_element_22' => $stripePaymentElementConfig2,
            ]);

        $paymentMethodViews = $this->provider->getPaymentMethodViews(
            ['stripe_payment_element_11', 'stripe_payment_element_22']
        );
        self::assertCount(2, $paymentMethodViews);

        $expectedPaymentMethodView1 = new StripePaymentElementMethodView(
            $stripePaymentElementConfig1,
            $this->scriptEnabledProvider,
            $this->stripeAmountConverter,
            $this->eventDispatcher
        );
        self::assertEquals($expectedPaymentMethodView1, $paymentMethodViews[0]);
        self::assertSame('stripe_payment_element_11', $paymentMethodViews[0]->getPaymentMethodIdentifier());

        $expectedPaymentMethodView2 = new StripePaymentElementMethodView(
            $stripePaymentElementConfig2,
            $this->scriptEnabledProvider,
            $this->stripeAmountConverter,
            $this->eventDispatcher
        );
        self::assertEquals($expectedPaymentMethodView2, $paymentMethodViews[1]);
        self::assertSame('stripe_payment_element_22', $paymentMethodViews[1]->getPaymentMethodIdentifier());
    }

    public function testViewsAreBuiltOnlyOnce(): void
    {
        $stripePaymentElementConfig = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_element_11',
        ]);
        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        // Multiple calls should only build views once
        $this->provider->hasPaymentMethodView('stripe_payment_element_11');
        $this->provider->getPaymentMethodView('stripe_payment_element_11');
        $this->provider->getPaymentMethodViews(['stripe_payment_element_11']);
    }
}
