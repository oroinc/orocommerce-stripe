<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Integration;

use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use PHPUnit\Framework\TestCase;

class StripeChannelTypeTest extends TestCase
{
    private StripeChannelType $channel;

    protected function setUp(): void
    {
        $this->channel = new StripeChannelType();
    }

    public function testGetLabelReturnsString(): void
    {
        $this->assertEquals('oro.stripe.channel_type.label', $this->channel->getLabel());
    }

    public function testGetIconReturnsString(): void
    {
        $this->assertEquals('bundles/orostripe/img/stripe-logo.png', $this->channel->getIcon());
    }
}
