<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Test\StripeClient;

use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClientInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Stripe\StripeClient;

/**
 * Creates a mocking StripeClient suitable for using in tests.
 */
class MockingStripeClientFactory implements StripeClientFactoryInterface
{
    #[\Override]
    public function createStripeClient(
        ?StripeClientConfigInterface $stripeConfig = null
    ): StripeClient&LoggingStripeClientInterface {
        return MockingStripeClient::instance();
    }
}
