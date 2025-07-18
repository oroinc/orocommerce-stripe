<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripeScript;

/**
 * Interface for Stripe script configuration.
 */
interface StripeScriptConfigInterface
{
    public function getApiPublicKey(): string;

    public function getScriptVersion(): string;

    public function getLocale(): string;

    public function isUserMonitoringEnabled(): bool;
}
