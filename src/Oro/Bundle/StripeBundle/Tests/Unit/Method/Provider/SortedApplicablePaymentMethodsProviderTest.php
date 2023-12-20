<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Provider;

use Oro\Bundle\CacheBundle\Provider\MemoryCacheProviderInterface;
use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodConfig;
use Oro\Bundle\PaymentBundle\Entity\PaymentMethodsConfigsRule;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\MethodsConfigsRule\Context\MethodsConfigsRulesByContextProviderInterface;
use Oro\Bundle\StripeBundle\Method\Provider\SortedApplicablePaymentMethodsProvider;

class SortedApplicablePaymentMethodsProviderTest extends \PHPUnit\Framework\TestCase
{
    /** @var PaymentMethodProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentMethodProvider;

    /** @var MethodsConfigsRulesByContextProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $paymentMethodsConfigsRulesProvider;

    /** @var MemoryCacheProviderInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $memoryCacheProvider;

    /** @var SortedApplicablePaymentMethodsProvider */
    private $provider;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->paymentMethodsConfigsRulesProvider = $this->createMock(
            MethodsConfigsRulesByContextProviderInterface::class
        );
        $this->memoryCacheProvider = $this->createMock(MemoryCacheProviderInterface::class);

        $this->provider = new SortedApplicablePaymentMethodsProvider(
            $this->paymentMethodProvider,
            $this->paymentMethodsConfigsRulesProvider,
            $this->memoryCacheProvider
        );
    }

    private function getPaymentMethod(): PaymentMethodInterface
    {
        $method = $this->createMock(PaymentMethodInterface::class);
        $method->expects(self::any())
            ->method('isApplicable')
            ->willReturn(true);

        return $method;
    }

    private function getPaymentMethodConfig(string $configuredMethodType): PaymentMethodConfig
    {
        $methodConfig = $this->createMock(PaymentMethodConfig::class);
        $methodConfig->expects(self::any())
            ->method('getType')
            ->willReturn($configuredMethodType);

        return $methodConfig;
    }

    private function getPaymentMethodsConfigsRule(array $configuredMethodTypes): PaymentMethodsConfigsRule
    {
        $methodConfigs = [];
        foreach ($configuredMethodTypes as $configuredMethodType) {
            $methodConfigs[] = $this->getPaymentMethodConfig($configuredMethodType);
        }

        $configsRule = $this->createMock(PaymentMethodsConfigsRule::class);
        $configsRule->expects(self::any())
            ->method('getMethodConfigs')
            ->willReturn($methodConfigs);

        return $configsRule;
    }

    public function testGetApplicablePaymentMethods(): void
    {
        $paymentContext = $this->createMock(PaymentContextInterface::class);

        $configsRules[] = $this->getPaymentMethodsConfigsRule(['SomeType']);
        $configsRules[] = $this->getPaymentMethodsConfigsRule(['PayPal', 'SomeOtherType']);
        $configsRules[] = $this->getPaymentMethodsConfigsRule(['Stripe', 'Stripe_apple_google_pay']);
        $configsRules[] = $this->getPaymentMethodsConfigsRule(
            ['Stripe_apple_google_pay_2', 'Stripe_2_apple_google_pay']
        );

        $someTypeMethod = $this->getPaymentMethod();
        $payPalMethod = $this->getPaymentMethod();
        $someOtherTypeMethod = $this->getPaymentMethod();
        $stripeMethod = $this->getPaymentMethod();
        $stripeAppleGooglePayMethod = $this->getPaymentMethod();
        $stripeAppleGooglePay2Method = $this->getPaymentMethod();
        $stripe2AppleGooglePayMethod = $this->getPaymentMethod();

        $this->memoryCacheProvider->expects(self::once())
            ->method('get')
            ->with(self::identicalTo(['payment_context' => $paymentContext]))
            ->willReturnCallback(function ($arguments, $callable) {
                return $callable($arguments);
            });

        $this->paymentMethodsConfigsRulesProvider->expects(self::once())
            ->method('getPaymentMethodsConfigsRules')
            ->with($paymentContext)
            ->willReturn($configsRules);

        $this->paymentMethodProvider->expects(self::exactly(7))
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

        $this->paymentMethodProvider->expects(self::exactly(7))
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

        self::assertSame(
            $expectedPaymentMethods,
            $this->provider->getApplicablePaymentMethods($paymentContext)
        );
    }

    public function testGetApplicablePaymentMethodsWhenDataCached(): void
    {
        $paymentContext = $this->createMock(PaymentContextInterface::class);

        $cachedPaymentMethods = [
            'SomeType' => $this->getPaymentMethod()
        ];

        $this->memoryCacheProvider->expects(self::once())
            ->method('get')
            ->with(self::identicalTo(['payment_context' => $paymentContext]))
            ->willReturnCallback(function () use ($cachedPaymentMethods) {
                return $cachedPaymentMethods;
            });

        $this->paymentMethodsConfigsRulesProvider->expects(self::never())
            ->method('getPaymentMethodsConfigsRules');

        $this->paymentMethodProvider->expects(self::never())
            ->method('hasPaymentMethod');

        $this->paymentMethodProvider->expects(self::never())
            ->method('getPaymentMethod');

        self::assertSame(
            $cachedPaymentMethods,
            $this->provider->getApplicablePaymentMethods($paymentContext)
        );
    }
}
