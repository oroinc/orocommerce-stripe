<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Placeholder;

use Oro\Bundle\StripeBundle\Placeholder\StripeFilter;
use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class StripeFilterTest extends TestCase
{
    private RequestStack&MockObject $requestStack;
    private StripeEnabledMonitoringCachedProvider&MockObject $provider;
    private StripeFilter $filter;

    #[\Override]
    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->provider = $this->createMock(StripeEnabledMonitoringCachedProvider::class);
        $this->filter = new StripeFilter(
            $this->requestStack,
            $this->provider
        );
        $this->filter->setAllowedRoutes(['oro_checkout_frontend_checkout']);
    }

    public function testIsApplicableOnCheckoutPage(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'oro_checkout_frontend_checkout');

        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->provider->expects($this->once())
            ->method('isStripeEnabled')
            ->willReturn(true);

        $this->provider->expects($this->never())
            ->method('isStripeMonitoringEnabled');

        $this->assertTrue($this->filter->isApplicable());
    }

    public function testIsApplicableOnAnotherPage(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'oro_sample_payment_page');

        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->provider->expects($this->once())
            ->method('isStripeEnabled')
            ->willReturn(true);

        $this->provider->expects($this->never())
            ->method('isStripeMonitoringEnabled');

        $this->filter->setAllowedRoutes(['oro_sample_payment_page']);
        $this->assertTrue($this->filter->isApplicable());
    }

    public function testIsApplicableWhenMonitoringEnabled(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test');

        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->provider->expects($this->once())
            ->method('isStripeEnabled')
            ->willReturn(true);

        $this->provider->expects($this->once())
            ->method('isStripeMonitoringEnabled')
            ->willReturn(true);

        $this->assertTrue($this->filter->isApplicable());
    }

    public function testIsApplicableFailed(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'test');

        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->provider->expects($this->once())
            ->method('isStripeEnabled')
            ->willReturn(true);

        $this->provider->expects($this->once())
            ->method('isStripeMonitoringEnabled')
            ->willReturn(false);

        $this->assertFalse($this->filter->isApplicable());
    }

    public function testIsApplicableWithDisabledStripeFailed(): void
    {
        $this->requestStack->expects($this->never())
            ->method('getMainRequest');

        $this->provider->expects($this->once())
            ->method('isStripeEnabled')
            ->willReturn(false);

        $this->provider->expects($this->never())
            ->method('isStripeMonitoringEnabled');

        $this->assertFalse($this->filter->isApplicable());
    }
}
