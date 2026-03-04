<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\ConfigProvider;
use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\EventListener\AddSetupFutureUsageToStripePaymentElementViewOptionsListener;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodView;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AddSetupFutureUsageToStripePaymentElementViewOptionsListenerTest extends TestCase
{
    private AddSetupFutureUsageToStripePaymentElementViewOptionsListener $listener;

    private MockObject&ConfigProvider $multiShippingConfigProvider;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    #[\Override]
    protected function setUp(): void
    {
        $this->multiShippingConfigProvider = $this->createMock(ConfigProvider::class);
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);

        $this->listener = new AddSetupFutureUsageToStripePaymentElementViewOptionsListener(
            $this->multiShippingConfigProvider
        );
    }

    public function testOnStripePaymentElementViewOptionsWhenSetupFutureUsageAlreadySet(): void
    {
        $paymentContext = new PaymentContext([]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                    'setupFutureUsage' => 'on_session',
                ],
            ]
        );

        $this->multiShippingConfigProvider
            ->expects(self::never())
            ->method('isCreateSubOrdersForEachGroupEnabled');

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                    'setupFutureUsage' => 'on_session',
                ],
            ],
            $event->getViewOptions()
        );
    }

    public function testOnStripePaymentElementViewOptionsWhenSourceIsNotCheckout(): void
    {
        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_SOURCE_ENTITY => new \stdClass(),
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [],
            ]
        );

        $this->multiShippingConfigProvider
            ->expects(self::never())
            ->method('isCreateSubOrdersForEachGroupEnabled');

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [],
            ],
            $event->getViewOptions()
        );
    }

    public function testOnStripePaymentElementViewOptionsWhenMultiShippingIsDisabled(): void
    {
        $checkout = new Checkout();

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_SOURCE_ENTITY => $checkout,
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [],
            ]
        );

        $this->multiShippingConfigProvider
            ->expects(self::once())
            ->method('isCreateSubOrdersForEachGroupEnabled')
            ->willReturn(false);

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [],
            ],
            $event->getViewOptions()
        );
    }

    public function testOnStripePaymentElementViewOptionsWhenMultiShippingIsEnabled(): void
    {
        $checkout = new Checkout();

        $paymentContext = new PaymentContext([
            PaymentContext::FIELD_SOURCE_ENTITY => $checkout,
        ]);

        $event = new StripePaymentElementViewOptionsEvent(
            $paymentContext,
            $this->stripePaymentElementConfig,
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [],
            ]
        );

        $this->multiShippingConfigProvider
            ->expects(self::once())
            ->method('isCreateSubOrdersForEachGroupEnabled')
            ->willReturn(true);

        $this->listener->onStripePaymentElementViewOptions($event);

        self::assertEquals(
            [
                StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                    'setupFutureUsage' => 'off_session',
                ],
            ],
            $event->getViewOptions()
        );
    }
}
