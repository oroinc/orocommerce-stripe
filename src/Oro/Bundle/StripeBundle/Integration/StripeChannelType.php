<?php

namespace Oro\Bundle\StripeBundle\Integration;

use Oro\Bundle\IntegrationBundle\Provider\ChannelInterface;
use Oro\Bundle\IntegrationBundle\Provider\IconAwareIntegrationInterface;

/**
 * Channel type description.
 */
class StripeChannelType implements ChannelInterface, IconAwareIntegrationInterface
{
    public const TYPE = 'stripe';

    #[\Override]
    public function getLabel(): string
    {
        return 'oro.stripe.channel_type.label';
    }

    #[\Override]
    public function getIcon(): string
    {
        return 'bundles/orostripe/img/stripe-logo.png';
    }
}
