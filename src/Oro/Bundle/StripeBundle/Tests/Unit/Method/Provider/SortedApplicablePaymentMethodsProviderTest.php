<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Provider;

use Oro\Bundle\PaymentBundle\Tests\Unit\Method\Provider\ApplicablePaymentMethodsProviderTest;
use Oro\Bundle\StripeBundle\Method\Provider\SortedApplicablePaymentMethodsProvider;

class SortedApplicablePaymentMethodsProviderTest extends ApplicablePaymentMethodsProviderTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new SortedApplicablePaymentMethodsProvider(
            $this->paymentMethodProvider,
            $this->paymentMethodsConfigsRulesProvider
        );
    }

    public function testGetApplicablePaymentMethods()
    {
        $configsRules[] = $this->getPaymentMethodsConfigsRule(['SomeType']);
        $configsRules[] = $this->getPaymentMethodsConfigsRule(['PayPal', 'SomeOtherType']);
        $configsRules[] = $this->getPaymentMethodsConfigsRule(['Stripe', 'Stripe_apple_google_pay']);
        $configsRules[] = $this->getPaymentMethodsConfigsRule(
            ['Stripe_apple_google_pay_2', 'Stripe_2_apple_google_pay']
        );

        $this->paymentMethodsConfigsRulesProvider->expects($this->once())
            ->method('getPaymentMethodsConfigsRules')
            ->with($this->paymentContext)
            ->willReturn($configsRules);

        $someTypeMethod = $this->getPaymentMethod('SomeType');
        $payPalMethod = $this->getPaymentMethod('PayPal');
        $someOtherTypeMethod = $this->getPaymentMethod('SomeOtherType');
        $stripeMethod = $this->getPaymentMethod('Stripe');
        $stripeAppleGooglePayMethod = $this->getPaymentMethod('Stripe_apple_google_pay');
        $stripeAppleGooglePay2Method = $this->getPaymentMethod('Stripe_apple_google_pay_2');
        $stripe2AppleGooglePayMethod = $this->getPaymentMethod('Stripe_2_apple_google_pay');

        $this->paymentMethodProvider->expects($this->any())
            ->method('hasPaymentMethod')
            ->willReturnMap([
                ['SomeType', true],
                ['PayPal', true],
                ['SomeOtherType', true],
                ['Stripe', true],
                ['Stripe_apple_google_pay', true],
                ['Stripe_apple_google_pay_2', true],
                ['Stripe_2_apple_google_pay', true],
            ]);

        $this->paymentMethodProvider->expects($this->any())
            ->method('getPaymentMethod')
            ->willReturnMap([
                ['SomeType', $someTypeMethod],
                ['PayPal', $payPalMethod],
                ['SomeOtherType', $someOtherTypeMethod],
                ['Stripe', $stripeMethod],
                ['Stripe_apple_google_pay', $stripeAppleGooglePayMethod],
                ['Stripe_apple_google_pay_2', $stripeAppleGooglePay2Method],
                ['Stripe_2_apple_google_pay', $stripe2AppleGooglePayMethod],
            ]);

        $expectedPaymentMethods = [
            'Stripe_apple_google_pay' => $stripeAppleGooglePayMethod,
            'Stripe_2_apple_google_pay' => $stripe2AppleGooglePayMethod,
            'SomeType' => $someTypeMethod,
            'PayPal' => $payPalMethod,
            'SomeOtherType' => $someOtherTypeMethod,
            'Stripe' => $stripeMethod,
            'Stripe_apple_google_pay_2' => $stripeAppleGooglePay2Method,
        ];

        $paymentMethods = $this->provider->getApplicablePaymentMethods($this->paymentContext);

        $this->assertSame($expectedPaymentMethods, $paymentMethods);
    }
}
