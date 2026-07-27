<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeScript\Provider;

use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripePaymentElementRouteAwareStripeScriptProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentElementRouteAwareStripeScriptProviderTest extends TestCase
{
    private StripePaymentElementConfigProvider&MockObject $configProvider;
    private RequestStack&MockObject $requestStack;
    private TokenAccessorInterface&MockObject $tokenAccessor;
    private CacheInterface&MockObject $cache;
    private StripePaymentElementRouteAwareStripeScriptProvider $provider;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(StripePaymentElementConfigProvider::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->provider = new StripePaymentElementRouteAwareStripeScriptProvider(
            $this->configProvider,
            $this->requestStack,
            $this->tokenAccessor,
            $this->cache
        );
    }

    public function testGetStripeScriptEnabledCacheKey(): void
    {
        $cacheKey = StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptEnabledCacheKey(123);
        self::assertSame('stripe_script_enabled_on_route|123', $cacheKey);
    }

    public function testGetStripeScriptVersionCacheKey(): void
    {
        $cacheKey = StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptVersionCacheKey(456);
        self::assertSame('stripe_script_version_on_route|456', $cacheKey);
    }

    public function testIsStripeScriptEnabledWithNoMainRequest(): void
    {
        $this->requestStack
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn(null);

        $result = $this->provider->isStripeScriptEnabled();
        self::assertFalse($result);
    }

    public function testIsStripeScriptEnabledWithDisallowedRoute(): void
    {
        $this->provider->setAllowedRoutes(['oro_checkout_frontend_checkout']);

        $request = new Request();
        $request->attributes->set('_route', 'some_other_route');

        $this->requestStack
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $result = $this->provider->isStripeScriptEnabled();
        self::assertFalse($result);
    }

    public function testIsStripeScriptEnabledWithAllowedRouteAndCacheHit(): void
    {
        $this->provider->setAllowedRoutes(['oro_checkout_frontend_checkout']);

        $request = new Request();
        $request->attributes->set('_route', 'oro_checkout_frontend_checkout');

        $this->requestStack
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(123);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptEnabledCacheKey(123),
                self::anything()
            )
            ->willReturn(true);

        $result = $this->provider->isStripeScriptEnabled();
        self::assertTrue($result);
    }

    public function testIsStripeScriptEnabledWithAllowedRouteAndCacheMiss(): void
    {
        $this->provider->setAllowedRoutes(['oro_checkout_frontend_checkout']);

        $request = new Request();
        $request->attributes->set('_route', 'oro_checkout_frontend_checkout');

        $this->requestStack
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(123);

        $config1 = $this->createMock(StripePaymentElementConfig::class);
        $config2 = $this->createMock(StripePaymentElementConfig::class);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptEnabledCacheKey(123),
                self::anything()
            )
            ->willReturnCallback(function ($key, $callback) use ($config1, $config2) {
                $this->configProvider
                    ->expects(self::once())
                    ->method('getPaymentConfigs')
                    ->willReturn(['config1' => $config1, 'config2' => $config2]);

                return $callback();
            });

        $result = $this->provider->isStripeScriptEnabled();
        self::assertTrue($result);
    }

    public function testIsStripeScriptEnabledWithNoConfigs(): void
    {
        $this->provider->setAllowedRoutes(['oro_checkout_frontend_checkout']);

        $request = new Request();
        $request->attributes->set('_route', 'oro_checkout_frontend_checkout');

        $this->requestStack
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(123);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptEnabledCacheKey(123),
                self::anything()
            )
            ->willReturnCallback(function ($key, $callback) {
                $this->configProvider
                    ->expects(self::once())
                    ->method('getPaymentConfigs')
                    ->willReturn([]);

                return $callback();
            });

        $result = $this->provider->isStripeScriptEnabled();
        self::assertFalse($result);
    }

    public function testIsStripeScriptEnabledWithNullOrganizationId(): void
    {
        $this->provider->setAllowedRoutes(['oro_checkout_frontend_checkout']);

        $request = new Request();
        $request->attributes->set('_route', 'oro_checkout_frontend_checkout');

        $this->requestStack
            ->expects(self::once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(null);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptEnabledCacheKey(0),
                self::anything()
            )
            ->willReturn(false);

        $result = $this->provider->isStripeScriptEnabled();
        self::assertFalse($result);
    }

    public function testGetStripeScriptVersionWithCacheHit(): void
    {
        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(456);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptVersionCacheKey(456),
                self::anything()
            )
            ->willReturn('v1.2.3');

        $result = $this->provider->getStripeScriptVersion();
        self::assertSame('v1.2.3', $result);
    }

    public function testGetStripeScriptVersionWithCacheMiss(): void
    {
        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(456);

        $config1 = $this->createMock(StripePaymentElementConfig::class);
        $config1
            ->expects(self::once())
            ->method('getScriptVersion')
            ->willReturn('v2.0.0');

        $config2 = $this->createMock(StripePaymentElementConfig::class);
        $config2
            ->expects(self::never())
            ->method('getScriptVersion');

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptVersionCacheKey(456),
                self::anything()
            )
            ->willReturnCallback(function ($key, $callback) use ($config1, $config2) {
                $this->configProvider
                    ->expects(self::once())
                    ->method('getPaymentConfigs')
                    ->willReturn(['config1' => $config1, 'config2' => $config2]);

                return $callback();
            });

        $result = $this->provider->getStripeScriptVersion();
        self::assertSame('v2.0.0', $result);
    }

    public function testGetStripeScriptVersionWithNoConfigs(): void
    {
        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(456);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptVersionCacheKey(456),
                self::anything()
            )
            ->willReturnCallback(function ($key, $callback) {
                $this->configProvider
                    ->expects(self::once())
                    ->method('getPaymentConfigs')
                    ->willReturn([]);

                return $callback();
            });

        $result = $this->provider->getStripeScriptVersion();
        self::assertSame('', $result);
    }

    public function testGetStripeScriptVersionWithNullOrganizationId(): void
    {
        $this->tokenAccessor
            ->expects(self::once())
            ->method('getOrganizationId')
            ->willReturn(null);

        $this->cache
            ->expects(self::once())
            ->method('get')
            ->with(
                StripePaymentElementRouteAwareStripeScriptProvider::getStripeScriptVersionCacheKey(0),
                self::anything()
            )
            ->willReturn('');

        $result = $this->provider->getStripeScriptVersion();
        self::assertSame('', $result);
    }
}
