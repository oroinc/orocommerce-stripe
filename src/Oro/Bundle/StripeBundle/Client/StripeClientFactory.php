<?php

namespace Oro\Bundle\StripeBundle\Client;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Create gateway based on Stripe SDK to perform online payments.
 */
class StripeClientFactory implements StripeGatewayFactoryInterface
{
    #[\Override]
    public function create(StripePaymentConfig $config): StripeGatewayInterface
    {
        if (!$config->getSecretKey()) {
            throw new \LogicException('Unable to initialize Stripe client: "Secret Key" is not configured');
        }

        return new StripeGateway($config->getSecretKey());
    }
}
