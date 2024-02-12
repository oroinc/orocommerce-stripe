<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Layout\DataProvider;

use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Provider\CheckoutPaymentContextProvider;
use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\ApplicablePaymentMethodsProvider;
use Oro\Bundle\StripeBundle\Layout\DataProvider\StripePaymentMethodsDataProvider;
use Oro\Bundle\StripeBundle\Method\Provider\StripePaymentMethodsProvider;
use Oro\Bundle\StripeBundle\Method\StripeAppleGooglePaymentMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodsDataProviderTest extends TestCase
{
    private CheckoutPaymentContextProvider|MockObject $checkoutPaymentContextProvider;
    private ApplicablePaymentMethodsProvider|MockObject $applicablePaymentMethodsProvider;
    private StripePaymentMethodsProvider|MockObject $stripePaymentMethodsProvider;
    private StripePaymentMethodsDataProvider $stripePaymentMethodsDataProvider;

    protected function setUp(): void
    {
        $this->checkoutPaymentContextProvider = $this->createMock(CheckoutPaymentContextProvider::class);
        $this->applicablePaymentMethodsProvider = $this->createMock(ApplicablePaymentMethodsProvider::class);
        $this->stripePaymentMethodsProvider = $this->createMock(StripePaymentMethodsProvider::class);

        $this->stripePaymentMethodsDataProvider = new StripePaymentMethodsDataProvider(
            $this->checkoutPaymentContextProvider,
            $this->applicablePaymentMethodsProvider,
            $this->stripePaymentMethodsProvider
        );
    }

    public function testGetStripePaymentMethodNames()
    {
        $checkout = new Checkout();
        $paymentContext = $this->createMock(PaymentContextInterface::class);

        $generalPaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $generalPaymentMethod->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('payment_method');

        $stripePaymentMethod = $this->createMock(StripeAppleGooglePaymentMethod::class);
        $stripePaymentMethod->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('stripe_1');

        $stripeAppleGooglePaymentMethod = $this->createMock(StripeAppleGooglePaymentMethod::class);
        $stripeAppleGooglePaymentMethod->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('stripe_1_apple_google_pay');

        $unavailableStripePaymentMethod = $this->createMock(PaymentMethodInterface::class);
        $unavailableStripePaymentMethod->expects($this->any())
            ->method('getIdentifier')
            ->willReturn('unavailable_stripe');

        $applicableMethods = [
            $generalPaymentMethod->getIdentifier() => $generalPaymentMethod,
            $stripePaymentMethod->getIdentifier() => $stripePaymentMethod,
            $stripeAppleGooglePaymentMethod->getIdentifier() => $stripeAppleGooglePaymentMethod,
        ];

        $stripeMethods = [
            $stripePaymentMethod->getIdentifier() => $stripePaymentMethod,
            $stripeAppleGooglePaymentMethod->getIdentifier() => $stripeAppleGooglePaymentMethod,
            $unavailableStripePaymentMethod->getIdentifier() => $unavailableStripePaymentMethod,
        ];

        $this->checkoutPaymentContextProvider->expects($this->once())
            ->method('getContext')
            ->with($checkout)
            ->willReturn($paymentContext);

        $this->applicablePaymentMethodsProvider->expects($this->once())
            ->method('getApplicablePaymentMethods')
            ->with($paymentContext)
            ->willReturn($applicableMethods);

        $this->stripePaymentMethodsProvider->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn($stripeMethods);

        $this->assertEquals(
            ['stripe_1'],
            $this->stripePaymentMethodsDataProvider->getStripePaymentMethodNames($checkout)
        );
    }

    public function testGetStripePaymentMethodNamesNoPaymentContext()
    {
        $checkout = new Checkout();

        $this->checkoutPaymentContextProvider->expects($this->once())
            ->method('getContext')
            ->with($checkout)
            ->willReturn(null);

        $this->assertEquals(
            [],
            $this->stripePaymentMethodsDataProvider->getStripePaymentMethodNames($checkout)
        );
    }
}
