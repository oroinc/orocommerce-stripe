<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeScript\Provider;

use Oro\Bundle\StripeBundle\Placeholder\StripeFilter;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeCardElementStripeScriptProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripeCardElementStripeScriptProviderTest extends TestCase
{
    private StripeCardElementStripeScriptProvider $provider;

    private MockObject&StripeFilter $stripeFilter;

    protected function setUp(): void
    {
        $this->stripeFilter = $this->createMock(StripeFilter::class);
        $this->provider = new StripeCardElementStripeScriptProvider(
            $this->stripeFilter,
            'v3'
        );
    }

    public function testIsStripeScriptEnabledWhenFilterIsApplicable(): void
    {
        $this->stripeFilter
            ->expects(self::once())
            ->method('isApplicable')
            ->willReturn(true);

        self::assertTrue($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenFilterIsNotApplicable(): void
    {
        $this->stripeFilter
            ->expects(self::once())
            ->method('isApplicable')
            ->willReturn(false);

        self::assertFalse($this->provider->isStripeScriptEnabled());
    }

    public function testGetStripeScriptVersionReturnsDefaultVersion(): void
    {
        self::assertSame('v3', $this->provider->getStripeScriptVersion());
    }

    public function testGetStripeScriptVersionReturnsCustomVersion(): void
    {
        $customVersion = 'basil';
        $provider = new StripeCardElementStripeScriptProvider(
            $this->stripeFilter,
            $customVersion
        );

        self::assertSame($customVersion, $provider->getStripeScriptVersion());
    }

    public function testConstructorWithEmptyScriptVersion(): void
    {
        $provider = new StripeCardElementStripeScriptProvider(
            $this->stripeFilter,
            ''
        );

        self::assertSame('', $provider->getStripeScriptVersion());
    }
}
