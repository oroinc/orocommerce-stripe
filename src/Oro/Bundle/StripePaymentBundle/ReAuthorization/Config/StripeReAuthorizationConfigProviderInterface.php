<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\Config;

/**
 * Provides re-authorization config for the given payment method identifier.
 */
interface StripeReAuthorizationConfigProviderInterface
{
    public function getReAuthorizationConfig(
        string $paymentMethodIdentifier
    ): ?StripeReAuthorizationConfigInterface;
}
