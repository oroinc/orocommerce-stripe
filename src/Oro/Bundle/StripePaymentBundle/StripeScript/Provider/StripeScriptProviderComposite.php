<?php

namespace Oro\Bundle\StripePaymentBundle\StripeScript\Provider;

/**
 * Provides Stripe script state and version to enable on a page by delegating calls to inner providers.
 */
class StripeScriptProviderComposite implements StripeScriptProviderInterface
{
    /**
     * @param iterable<StripeScriptProviderInterface> $stripeScriptProviders
     */
    public function __construct(
        private iterable $stripeScriptProviders
    ) {
    }

    #[\Override]
    public function isStripeScriptEnabled(): bool
    {
        foreach ($this->stripeScriptProviders as $stripeScriptProvider) {
            if ($stripeScriptProvider->isStripeScriptEnabled()) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function getStripeScriptVersion(): string
    {
        foreach ($this->stripeScriptProviders as $stripeScriptProvider) {
            if ($stripeScriptProvider->isStripeScriptEnabled()) {
                return $stripeScriptProvider->getStripeScriptVersion();
            }
        }

        return '';
    }
}
