<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Behat\Mock\StripeClient;

use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClientInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Stripe\StripeClient;

/**
 * Creates a Stripe client stub suitable for using in behat tests.
 */
class StubStripeClientFactory implements StripeClientFactoryInterface
{
    private ?StubStripeClient $stubClient = null;

    #[\Override]
    public function createStripeClient(
        StripeClientConfigInterface $stripeConfig
    ): StripeClient&LoggingStripeClientInterface {
        return $this->stubClient ??= new StubStripeClient();
    }
}
