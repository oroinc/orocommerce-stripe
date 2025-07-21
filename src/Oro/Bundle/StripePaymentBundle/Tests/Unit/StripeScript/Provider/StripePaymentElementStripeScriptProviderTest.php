<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeScript\Provider;

use Oro\Bundle\FrontendBundle\Request\FrontendHelper;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripePaymentElementStripeScriptProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class StripePaymentElementStripeScriptProviderTest extends TestCase
{
    private StripePaymentElementStripeScriptProvider $provider;

    private MockObject&StripePaymentElementConfigProvider $stripePaymentElementConfigProvider;

    private MockObject&FrontendHelper $frontendHelper;

    private MockObject&TokenAccessorInterface $tokenAccessor;

    private MockObject&CacheInterface $cache;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfigProvider = $this->createMock(StripePaymentElementConfigProvider::class);
        $this->frontendHelper = $this->createMock(FrontendHelper::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->provider = new StripePaymentElementStripeScriptProvider(
            $this->stripePaymentElementConfigProvider,
            $this->frontendHelper,
            $this->tokenAccessor,
            $this->cache
        );
    }

    public function testIsStripeScriptEnabledWhenNotFrontendRequest(): void
    {
        $this->frontendHelper
            ->expects(self::once())
            ->method('isFrontendRequest')
            ->willReturn(false);

        self::assertFalse($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenFrontendRequestAndCached(): void
    {
        $organizationId = 42;
        $cacheKey = StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey($organizationId);

        $this->frontendHelper
            ->expects(self::once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with($cacheKey, self::isType('callable'))
            ->willReturn(true);

        self::assertTrue($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenNoEnabledConfigs(): void
    {
        $organizationId = 42;
        $cacheKey = StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey($organizationId);

        $this->frontendHelper
            ->expects(self::once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with($cacheKey, self::isType('callable'))
            ->willReturnCallback(static fn ($key, $callback) => $callback());

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([]);

        self::assertFalse($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenConfigEnabled(): void
    {
        $organizationId = 42;
        $cacheKey = StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey($organizationId);

        $this->frontendHelper
            ->expects(self::once())
            ->method('isFrontendRequest')
            ->willReturn(true);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with($cacheKey, self::isType('callable'))
            ->willReturnCallback(static fn ($key, $callback) => $callback());

        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('isUserMonitoringEnabled')
            ->willReturn(true);

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        self::assertTrue($this->provider->isStripeScriptEnabled());
    }

    public function testGetStripeScriptVersionWhenCached(): void
    {
        $organizationId = 42;
        $cacheKey = StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey($organizationId);
        $scriptVersion = 'basil';

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with($cacheKey, self::isType('callable'))
            ->willReturn($scriptVersion);

        self::assertSame($scriptVersion, $this->provider->getStripeScriptVersion());
    }

    public function testGetStripeScriptVersionWhenNoEnabledConfigs(): void
    {
        $organizationId = 42;
        $cacheKey = StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey($organizationId);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with($cacheKey, self::isType('callable'))
            ->willReturnCallback(static fn ($key, $callback) => $callback());

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn([]);

        self::assertSame('', $this->provider->getStripeScriptVersion());
    }

    public function testGetStripeScriptVersionWhenConfigEnabled(): void
    {
        $organizationId = 42;
        $cacheKey = StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey($organizationId);
        $stripeVersion = 'basil';

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn($organizationId);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with($cacheKey, self::isType('callable'))
            ->willReturnCallback(fn ($key, $callback) => $callback());

        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('isUserMonitoringEnabled')
            ->willReturn(true);
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('getScriptVersion')
            ->willReturn($stripeVersion);

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfigs')
            ->willReturn(['stripe_payment_element_11' => $stripePaymentElementConfig]);

        self::assertSame($stripeVersion, $this->provider->getStripeScriptVersion());
    }

    public function testStaticCacheKeyMethods(): void
    {
        $organizationId = 123;
        $enabledKey = StripePaymentElementStripeScriptProvider::getStripeScriptEnabledCacheKey(
            $organizationId
        );
        $versionKey = StripePaymentElementStripeScriptProvider::getStripeScriptVersionCacheKey(
            $organizationId
        );

        self::assertSame('stripe_script_enabled|123', $enabledKey);
        self::assertSame('stripe_script_version|123', $versionKey);
    }
}
