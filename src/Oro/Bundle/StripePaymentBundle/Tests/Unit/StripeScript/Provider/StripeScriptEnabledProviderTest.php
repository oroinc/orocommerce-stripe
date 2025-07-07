<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeScript\Provider;

use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptEnabledProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

final class StripeScriptEnabledProviderTest extends TestCase
{
    private StripeScriptEnabledProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new StripeScriptEnabledProvider();
    }

    public function testImplementsRequiredInterfaces(): void
    {
        self::assertInstanceOf(ResetInterface::class, $this->provider);
    }

    public function testInitialState(): void
    {
        self::assertFalse($this->provider->isStripeScriptEnabled());
        self::assertSame('', $this->provider->getStripeScriptVersion());
    }

    public function testEnableStripeScript(): void
    {
        $version = 'v3';
        $this->provider->enableStripeScript($version);

        self::assertTrue($this->provider->isStripeScriptEnabled());
        self::assertSame($version, $this->provider->getStripeScriptVersion());
    }

    public function testEnableStripeScriptWithEmptyVersion(): void
    {
        $this->provider->enableStripeScript('');

        self::assertFalse($this->provider->isStripeScriptEnabled());
        self::assertSame('', $this->provider->getStripeScriptVersion());
    }

    public function testReset(): void
    {
        $this->provider->enableStripeScript('v3');
        $this->provider->reset();

        self::assertFalse($this->provider->isStripeScriptEnabled());
        self::assertSame('', $this->provider->getStripeScriptVersion());
    }

    public function testMultipleEnableCalls(): void
    {
        $this->provider->enableStripeScript('v3');
        $this->provider->enableStripeScript('basil');

        self::assertTrue($this->provider->isStripeScriptEnabled());
        self::assertSame('basil', $this->provider->getStripeScriptVersion());
    }

    public function testEnableAfterReset(): void
    {
        $this->provider->enableStripeScript('v3');
        $this->provider->reset();
        $this->provider->enableStripeScript('basil');

        self::assertTrue($this->provider->isStripeScriptEnabled());
        self::assertSame('basil', $this->provider->getStripeScriptVersion());
    }
}
