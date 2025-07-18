<?php

namespace Oro\Bundle\StripePaymentBundle\Integration\StripePaymentElement;

use Oro\Bundle\IntegrationBundle\Provider\ChannelInterface;
use Oro\Bundle\IntegrationBundle\Provider\IconAwareIntegrationInterface;

/**
 * Stripe Payment Element payment method integration channel.
 */
final class StripePaymentElementIntegrationChannelType implements ChannelInterface, IconAwareIntegrationInterface
{
    public const string TYPE = 'stripe_payment_element';

    #[\Override]
    public function getLabel(): string
    {
        return 'oro.stripe_payment.payment_element.label';
    }

    #[\Override]
    public function getIcon(): string
    {
        return 'bundles/orostripepayment/img/stripe-logo.png';
    }
}
