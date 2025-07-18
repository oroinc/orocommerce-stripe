<?php

namespace Oro\Bundle\StripePaymentBundle\StripeScript\Provider;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides an ability to explicitly enable the Stripe script on any page.
 */
class StripeScriptEnabledProvider implements StripeScriptProviderInterface, ResetInterface
{
    private string $stripeScriptVersion = '';

    /**
     * @param string $stripeScriptVersion Use to explicitly enable the Stripe script on any page.
     *  This method should be used on the payment page to load the Stripe script independently
     *  of the Stripe User Monitoring feature.
     */
    public function enableStripeScript(string $stripeScriptVersion): void
    {
        $this->stripeScriptVersion = $stripeScriptVersion;
    }

    #[\Override]
    public function isStripeScriptEnabled(): bool
    {
        return $this->stripeScriptVersion !== '';
    }

    #[\Override]
    public function getStripeScriptVersion(): string
    {
        return $this->stripeScriptVersion;
    }

    #[\Override]
    public function reset(): void
    {
        $this->stripeScriptVersion = '';
    }
}
