<?php

namespace Oro\Bundle\StripePaymentBundle\Placeholder;

use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptProviderInterface;

/**
 * Service implementation for "stripe_script" placeholder.
 */
class StripeScriptPlaceholder
{
    /**
     * @param StripeScriptProviderInterface $stripeScriptProvider
     * @param string $stripeScriptUrlPattern Stripe script URL pattern with version placeholder,
     *  e.g. https://js.stripe.com/%s/stripe.js
     */
    public function __construct(
        private StripeScriptProviderInterface $stripeScriptProvider,
        private string $stripeScriptUrlPattern
    ) {
    }

    public function isApplicable(): bool
    {
        return $this->stripeScriptProvider->isStripeScriptEnabled();
    }

    /**
     * @return array{stripe_script_url: string}
     */
    public function getData(): array
    {
        return [
            'stripe_script_url' => sprintf(
                $this->stripeScriptUrlPattern,
                $this->stripeScriptProvider->getStripeScriptVersion() ?: 'v3'
            ),
        ];
    }
}
