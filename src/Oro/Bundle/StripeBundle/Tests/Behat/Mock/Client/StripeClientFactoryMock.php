<?php

namespace Oro\Bundle\StripeBundle\Tests\Behat\Mock\Client;

use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

class StripeClientFactoryMock implements StripeGatewayFactoryInterface
{
    public function create(StripePaymentConfig $config): StripeGatewayInterface
    {
        if (!$config->getSecretKey()) {
            throw new \LogicException('Unable to initialize Stripe client: "Secret Key" is not configured');
        }

        return new StripeGatewayMock($config);
    }
}
