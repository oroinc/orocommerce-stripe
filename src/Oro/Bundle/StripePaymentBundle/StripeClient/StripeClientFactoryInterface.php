<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeClient;

use Stripe\StripeClient;

/**
 * Creates an instance of Stripe API client.
 */
interface StripeClientFactoryInterface
{
    public function createStripeClient(
        StripeClientConfigInterface $stripeConfig
    ): StripeClient&LoggingStripeClientInterface;
}
