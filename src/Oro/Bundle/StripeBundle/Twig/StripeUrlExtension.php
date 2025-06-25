<?php

namespace Oro\Bundle\StripeBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Implements logic to extend twig with new function which responsible for retrieving Stripe.js library url.
 */
class StripeUrlExtension extends AbstractExtension
{
    public function __construct(protected array $externalResources)
    {
    }

    #[\Override]
    public function getFunctions()
    {
        return [
            new TwigFunction('oro_stripe_url', [$this, 'getStripeLibraryUrl'])
        ];
    }

    public function getStripeLibraryUrl(): string
    {
        return $this->externalResources['stripe_js_library']['link'];
    }
}
