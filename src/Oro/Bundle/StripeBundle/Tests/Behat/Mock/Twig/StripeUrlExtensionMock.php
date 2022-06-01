<?php

namespace Oro\Bundle\StripeBundle\Tests\Behat\Mock\Twig;

use Oro\Bundle\StripeBundle\Twig\StripeUrlExtension;

class StripeUrlExtensionMock extends StripeUrlExtension
{
    public const STRIPE_LIBRARY_URL = '/bundles/orostripe/js/stubs/stripe-stub.js';
}
