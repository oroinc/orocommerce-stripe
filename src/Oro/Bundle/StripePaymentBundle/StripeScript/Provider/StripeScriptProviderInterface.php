<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeScript\Provider;

/**
 * Provides Stripe script state and version to enable on a page.
 */
interface StripeScriptProviderInterface
{
    public function isStripeScriptEnabled(): bool;

    /**
     * @return string Stripe script version (e.g. basil, acacia, v3).
     */
    public function getStripeScriptVersion(): string;
}
