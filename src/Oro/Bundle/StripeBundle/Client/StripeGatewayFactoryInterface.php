<?php

namespace Oro\Bundle\StripeBundle\Client;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Create gateway object to interact with Stripe services.
 */
interface StripeGatewayFactoryInterface
{
    public function create(StripePaymentConfig $config): StripeGatewayInterface;
}
