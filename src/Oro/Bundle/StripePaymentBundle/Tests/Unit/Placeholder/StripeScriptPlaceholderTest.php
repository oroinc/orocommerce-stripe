<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Placeholder;

use Oro\Bundle\StripePaymentBundle\Placeholder\StripeScriptPlaceholder;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripeScriptPlaceholderTest extends TestCase
{
    private StripeScriptPlaceholder $placeholder;

    private MockObject&StripeScriptProviderInterface $stripeScriptProvider;

    protected function setUp(): void
    {
        $this->stripeScriptProvider = $this->createMock(StripeScriptProviderInterface::class);
        $this->placeholder = new StripeScriptPlaceholder(
            $this->stripeScriptProvider,
            'https://js.stripe.com/%s/stripe.js'
        );
    }

    public function testIsApplicableWhenScriptIsEnabled(): void
    {
        $this->stripeScriptProvider
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(true);

        self::assertTrue($this->placeholder->isApplicable());
    }

    public function testIsApplicableWhenScriptIsDisabled(): void
    {
        $this->stripeScriptProvider
            ->expects(self::once())
            ->method('isStripeScriptEnabled')
            ->willReturn(false);

        self::assertFalse($this->placeholder->isApplicable());
    }

    public function testGetDataReturnsCorrectVersion(): void
    {
        $expectedVersion = 'basil';

        $this->stripeScriptProvider
            ->expects(self::once())
            ->method('getStripeScriptVersion')
            ->willReturn($expectedVersion);

        $result = $this->placeholder->getData();

        self::assertArrayHasKey('stripe_script_url', $result);
        self::assertSame('https://js.stripe.com/basil/stripe.js', $result['stripe_script_url']);
    }

    public function testGetDataReturnsEmptyVersionWhenNotAvailable(): void
    {
        $this->stripeScriptProvider
            ->expects(self::once())
            ->method('getStripeScriptVersion')
            ->willReturn('');

        $result = $this->placeholder->getData();

        self::assertArrayHasKey('stripe_script_url', $result);
        self::assertSame('https://js.stripe.com/v3/stripe.js', $result['stripe_script_url']);
    }
}
