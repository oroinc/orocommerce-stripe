<?php

namespace Oro\Bundle\StripeBundle\Tests\Behat\Mock\Layout\Extension\Generator;

use Oro\Bundle\LayoutBundle\Layout\Extension\Generator\ThemesRelativePathGeneratorExtension;

/**
 * Override layout path for the Stripe in frontend checkout.
 */
class ThemesRelativePathGeneratorExtensionMock extends ThemesRelativePathGeneratorExtension
{
    private static array $paths = [
        '@OroStripe/layouts/default/imports/oro_payment_method_options/layout.html.twig' =>
        '@OroStripe/layouts/mock/imports/oro_payment_method_options/layout.html.twig'
    ];

    /**
     * {@inheritDoc}
     */
    protected function prepareThemePath($theme, $file)
    {
        if (array_key_exists($theme, self::$paths)) {
            $theme = self::$paths[$theme];
        }

        return parent::prepareThemePath($theme, $file);
    }
}
