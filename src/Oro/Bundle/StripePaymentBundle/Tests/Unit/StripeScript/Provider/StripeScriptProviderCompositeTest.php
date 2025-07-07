<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeScript\Provider;

use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptProviderComposite;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripeScriptProviderCompositeTest extends TestCase
{
    private StripeScriptProviderComposite $provider;

    private MockObject&StripeScriptProviderInterface $provider1;

    private MockObject&StripeScriptProviderInterface $provider2;

    private MockObject&StripeScriptProviderInterface $provider3;

    protected function setUp(): void
    {
        $this->provider1 = $this->createMock(StripeScriptProviderInterface::class);
        $this->provider2 = $this->createMock(StripeScriptProviderInterface::class);
        $this->provider3 = $this->createMock(StripeScriptProviderInterface::class);

        $this->provider = new StripeScriptProviderComposite([
            $this->provider1,
            $this->provider2,
            $this->provider3,
        ]);
    }

    public function testIsStripeScriptEnabledWhenNoProvidersEnabled(): void
    {
        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider2
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider3
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        self::assertFalse($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenFirstProviderEnabled(): void
    {
        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(true);

        // Subsequent providers should not be checked
        $this->provider2
            ->expects(self::never())
            ->method('isStripeScriptEnabled');
        $this->provider3
            ->expects(self::never())
            ->method('isStripeScriptEnabled');

        self::assertTrue($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenMiddleProviderEnabled(): void
    {
        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider2
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(true);

        // Last provider should not be checked
        $this->provider3
            ->expects(self::never())
            ->method('isStripeScriptEnabled');

        self::assertTrue($this->provider->isStripeScriptEnabled());
    }

    public function testIsStripeScriptEnabledWhenLastProviderEnabled(): void
    {
        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider2
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider3
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(true);

        self::assertTrue($this->provider->isStripeScriptEnabled());
    }

    public function testGetStripeScriptVersionWhenNoProvidersEnabled(): void
    {
        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider2
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider3
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        self::assertSame('', $this->provider->getStripeScriptVersion());
    }

    public function testGetStripeScriptVersionWhenFirstProviderEnabled(): void
    {
        $scriptVersion = 'basil';

        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(true);
        $this->provider1
            ->expects(self::once())
            ->method('getStripeScriptVersion')
            ->willReturn($scriptVersion);

        // Subsequent providers should not be checked
        $this->provider2
            ->expects(self::never())
            ->method('isStripeScriptEnabled');
        $this->provider3
            ->expects(self::never())
            ->method('isStripeScriptEnabled');

        self::assertSame($scriptVersion, $this->provider->getStripeScriptVersion());
    }

    public function testGetStripeScriptVersionWhenMiddleProviderEnabled(): void
    {
        $scriptVersion = 'basil';

        $this->provider1
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        $this->provider2
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(true);
        $this->provider2
            ->expects(self::once())
            ->method('getStripeScriptVersion')
            ->willReturn($scriptVersion);

        // Last provider should not be checked
        $this->provider3
            ->expects(self::never())
            ->method('isStripeScriptEnabled');

        self::assertSame($scriptVersion, $this->provider->getStripeScriptVersion());
    }

    public function testEmptyProvidersList(): void
    {
        $provider = new StripeScriptProviderComposite([]);

        self::assertFalse($provider->isStripeScriptEnabled());
        self::assertSame('', $provider->getStripeScriptVersion());
    }
}
