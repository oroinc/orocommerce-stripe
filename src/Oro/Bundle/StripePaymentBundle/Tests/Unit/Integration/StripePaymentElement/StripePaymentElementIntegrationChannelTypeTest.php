<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Integration\StripePaymentElement;

use Oro\Bundle\IntegrationBundle\Provider\ChannelInterface;
use Oro\Bundle\IntegrationBundle\Provider\IconAwareIntegrationInterface;
use Oro\Bundle\StripePaymentBundle\Integration\StripePaymentElement\StripePaymentElementIntegrationChannelType;
use PHPUnit\Framework\TestCase;

final class StripePaymentElementIntegrationChannelTypeTest extends TestCase
{
    private StripePaymentElementIntegrationChannelType $channelType;

    protected function setUp(): void
    {
        $this->channelType = new StripePaymentElementIntegrationChannelType();
    }

    public function testImplementsRequiredInterfaces(): void
    {
        self::assertInstanceOf(ChannelInterface::class, $this->channelType);
        self::assertInstanceOf(IconAwareIntegrationInterface::class, $this->channelType);
    }

    public function testGetLabel(): void
    {
        self::assertSame(
            'oro.stripe_payment.payment_element.label',
            $this->channelType->getLabel()
        );
    }

    public function testGetIcon(): void
    {
        self::assertSame(
            'bundles/orostripepayment/img/stripe-logo.png',
            $this->channelType->getIcon()
        );
    }
}
