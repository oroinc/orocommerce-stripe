<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Placeholder;

use Oro\Bundle\StripeBundle\Placeholder\StripeFilter;
use Oro\Bundle\StripeBundle\Provider\StripeEnabledMonitoringCachedProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class StripeFilterTest extends TestCase
{
    /** @var RequestStack|\PHPUnit\Framework\MockObject\MockObject  */
    private RequestStack $requestStack;

    /** @var StripeEnabledMonitoringCachedProvider|\PHPUnit\Framework\MockObject\MockObject  */
    private StripeEnabledMonitoringCachedProvider $provider;
    private StripeFilter $filter;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->provider = $this->createMock(StripeEnabledMonitoringCachedProvider::class);
        $this->filter = new StripeFilter(
            $this->requestStack,
            $this->provider
        );
    }

    public function testIsApplicableOnCheckoutPage(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'oro_checkout_frontend_checkout');

        $this->requestStack->expects($this->once())
            ->method('getMainRequest')
            ->willReturn($request);

        $this->provider->expects($this->never())
            ->method('isStripeMonitoringEnabled');

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
            ->method('isStripeMonitoringEnabled')
            ->willReturn(false);

        $this->assertFalse($this->filter->isApplicable());
    }
}
