<?php

namespace Oro\Bundle\StripeBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Implements logic to extend twig with new function which responsible for retrieving Stripe.js library url.
 */
class StripeUrlExtension extends AbstractExtension
{
    public const STRIPE_LIBRARY_URL = 'https://js.stripe.com/v3/';

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new TwigFunction('oro_stripe_url', [$this, 'getStripeLibraryUrl'])
        ];
    }

    public function getStripeLibraryUrl(): string
    {
        return static::STRIPE_LIBRARY_URL;
    }
}
