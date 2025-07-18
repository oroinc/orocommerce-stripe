<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement\View;

use Oro\Bundle\CurrencyBundle\DependencyInjection\Configuration as CurrencyConfiguration;
use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\PaymentBundle\Method\View\PaymentMethodViewInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View\StripePaymentElementMethodView;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptEnabledProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class StripePaymentElementMethodViewTest extends TestCase
{
    private StripePaymentElementMethodView $view;

    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private MockObject&StripeScriptEnabledProvider $scriptEnabledProvider;

    private MockObject&StripeAmountConverterInterface $stripeAmountConverter;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->scriptEnabledProvider = $this->createMock(StripeScriptEnabledProvider::class);
        $this->stripeAmountConverter = $this->createMock(StripeAmountConverterInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->view = new StripePaymentElementMethodView(
            $this->stripePaymentElementConfig,
            $this->scriptEnabledProvider,
            $this->stripeAmountConverter,
            $this->eventDispatcher
        );
    }

    public function testImplementsRequiredInterfaces(): void
    {
        self::assertInstanceOf(PaymentMethodViewInterface::class, $this->view);
    }

    public function testGetAdminLabel(): void
    {
        $expectedLabel = 'Stripe Payment Element';

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getAdminLabel')
            ->willReturn($expectedLabel);

        self::assertSame($expectedLabel, $this->view->getAdminLabel());
    }

    public function testGetLabel(): void
    {
        $expectedLabel = 'Stripe Payment';

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getLabel')
            ->willReturn($expectedLabel);

        self::assertSame($expectedLabel, $this->view->getLabel());
    }

    public function testGetShortLabel(): void
    {
        $expectedLabel = 'Stripe';

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getShortLabel')
            ->willReturn($expectedLabel);

        self::assertSame($expectedLabel, $this->view->getShortLabel());
    }

    public function testGetPaymentMethodIdentifier(): void
    {
        $expectedIdentifier = 'stripe_payment_element_42';

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn($expectedIdentifier);

        self::assertSame($expectedIdentifier, $this->view->getPaymentMethodIdentifier());
    }

    public function testGetBlock(): void
    {
        $expectedBlock = 'oro_stripe_payment_element_widget';

        self::assertSame($expectedBlock, $this->view->getBlock());
    }

    public function testGetOptionsWithDefaultValues(): void
    {
        $scriptVersion = 'basil';
        $locale = 'en';
        $publicKey = 'pk_test_123';
        $totalAmount = 123.45;
        $currency = 'USD';
        $paymentContext = new PaymentContext(
            [PaymentContext::FIELD_TOTAL => $totalAmount, PaymentContext::FIELD_CURRENCY => $currency]
        );

        $this->scriptEnabledProvider
            ->expects(self::once())
            ->method('enableStripeScript')
            ->with($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getScriptVersion')
            ->willReturn($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getLocale')
            ->willReturn($locale);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getApiPublicKey')
            ->willReturn($publicKey);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $stripeAmount = 12345;
        $this->stripeAmountConverter
            ->expects(self::once())
            ->method('convertToStripeFormat')
            ->with($paymentContext->getTotal(), strtolower($currency))
            ->willReturn($stripeAmount);

        $expectedOptions = [
            StripePaymentElementMethodView::STRIPE_OPTIONS => [
                'locale' => $locale,
                'apiPublicKey' => $publicKey,
            ],
            StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                'amount' => $stripeAmount,
                'currency' => strtolower($currency),
                'mode' => 'payment',
                'captureMethod' => 'automatic',
            ],
            StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [],
        ];

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripePaymentElementViewOptionsEvent(
                    $paymentContext,
                    $this->stripePaymentElementConfig,
                    $expectedOptions
                )
            );

        self::assertEquals($expectedOptions, $this->view->getOptions($paymentContext));
    }

    public function testGetOptionsWithNullContextValues(): void
    {
        $scriptVersion = 'basil';
        $locale = 'en';
        $publicKey = 'pk_test_123';
        $paymentContext = new PaymentContext([]);

        $this->scriptEnabledProvider
            ->expects(self::once())
            ->method('enableStripeScript')
            ->with($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getScriptVersion')
            ->willReturn($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getLocale')
            ->willReturn($locale);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getApiPublicKey')
            ->willReturn($publicKey);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $this->stripeAmountConverter
            ->expects(self::once())
            ->method('convertToStripeFormat')
            ->with($paymentContext->getTotal(), strtolower(CurrencyConfiguration::DEFAULT_CURRENCY))
            ->willReturn(0);

        $expectedOptions = [
            StripePaymentElementMethodView::STRIPE_OPTIONS => [
                'locale' => $locale,
                'apiPublicKey' => $publicKey,
            ],
            StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                'amount' => 0,
                'currency' => strtolower(CurrencyConfiguration::DEFAULT_CURRENCY),
                'mode' => 'payment',
                'captureMethod' => 'automatic',
            ],
            StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [],
        ];

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripePaymentElementViewOptionsEvent(
                    $paymentContext,
                    $this->stripePaymentElementConfig,
                    $expectedOptions
                )
            );

        self::assertEquals($expectedOptions, $this->view->getOptions($paymentContext));
    }

    public function testGetOptionsWithReAuthorizationEnabled(): void
    {
        $scriptVersion = 'basil';
        $locale = 'en';
        $publicKey = 'pk_test_123';
        $totalAmount = 123.45;
        $currency = 'USD';
        $paymentContext = new PaymentContext(
            [PaymentContext::FIELD_TOTAL => $totalAmount, PaymentContext::FIELD_CURRENCY => $currency]
        );

        $this->scriptEnabledProvider
            ->expects(self::once())
            ->method('enableStripeScript')
            ->with($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getScriptVersion')
            ->willReturn($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getLocale')
            ->willReturn($locale);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getApiPublicKey')
            ->willReturn($publicKey);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('isReAuthorizationEnabled')
            ->willReturn(true);

        $stripeAmount = 12345;
        $this->stripeAmountConverter
            ->expects(self::once())
            ->method('convertToStripeFormat')
            ->with($paymentContext->getTotal(), strtolower($currency))
            ->willReturn($stripeAmount);

        $expectedOptions = [
            StripePaymentElementMethodView::STRIPE_OPTIONS => [
                'locale' => $locale,
                'apiPublicKey' => $publicKey,
            ],
            StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                'amount' => $stripeAmount,
                'currency' => strtolower($currency),
                'mode' => 'payment',
                'captureMethod' => 'automatic',
                'setupFutureUsage' => 'off_session',
            ],
            StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [],
        ];

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripePaymentElementViewOptionsEvent(
                    $paymentContext,
                    $this->stripePaymentElementConfig,
                    $expectedOptions
                )
            );

        self::assertEquals($expectedOptions, $this->view->getOptions($paymentContext));
    }

    public function testGetOptionsModifiesOptionsViaEventDispatcher(): void
    {
        $scriptVersion = 'basil';
        $locale = 'en';
        $publicKey = 'pk_test_123';
        $totalAmount = 123.45;
        $currency = 'USD';
        $paymentContext = new PaymentContext(
            [PaymentContext::FIELD_TOTAL => $totalAmount, PaymentContext::FIELD_CURRENCY => $currency]
        );

        $this->scriptEnabledProvider
            ->expects(self::once())
            ->method('enableStripeScript')
            ->with($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getScriptVersion')
            ->willReturn($scriptVersion);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getLocale')
            ->willReturn($locale);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('getApiPublicKey')
            ->willReturn($publicKey);

        $this->stripePaymentElementConfig
            ->expects(self::once())
            ->method('isReAuthorizationEnabled')
            ->willReturn(false);

        $stripeAmount = 12345;
        $this->stripeAmountConverter
            ->expects(self::once())
            ->method('convertToStripeFormat')
            ->with($paymentContext->getTotal(), strtolower($currency))
            ->willReturn($stripeAmount);

        $expectedOptions = [
            StripePaymentElementMethodView::STRIPE_OPTIONS => [
                'locale' => $locale,
                'apiPublicKey' => $publicKey,
            ],
            StripePaymentElementMethodView::STRIPE_ELEMENTS_OBJECT_OPTIONS => [
                'amount' => $stripeAmount,
                'currency' => strtolower($currency),
                'mode' => 'payment',
                'captureMethod' => 'automatic',
            ],
            StripePaymentElementMethodView::STRIPE_PAYMENT_ELEMENT_OPTIONS => [],
        ];

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripePaymentElementViewOptionsEvent(
                    $paymentContext,
                    $this->stripePaymentElementConfig,
                    $expectedOptions
                )
            )
            ->willReturnCallback(static function (StripePaymentElementViewOptionsEvent $event) use ($expectedOptions) {
                $expectedOptions['sampleKey'] = 'sampleValue';
                $event->setViewOptions($expectedOptions);

                return $event;
            });

        $options = $this->view->getOptions($paymentContext);

        $expectedOptions['sampleKey'] = 'sampleValue';
        self::assertEquals($expectedOptions, $options);
    }
}
