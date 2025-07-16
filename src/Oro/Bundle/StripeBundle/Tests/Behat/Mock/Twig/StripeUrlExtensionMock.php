<?php

namespace Oro\Bundle\StripeBundle\Tests\Behat\Mock\Twig;

use Oro\Bundle\StripeBundle\Twig\StripeUrlExtension;

class StripeUrlExtensionMock extends StripeUrlExtension
{
    public function getStripeLibraryUrl(): string
    {
        return '/bundles/orostripe/js/stubs/stripe-stub.js';
    }
}
