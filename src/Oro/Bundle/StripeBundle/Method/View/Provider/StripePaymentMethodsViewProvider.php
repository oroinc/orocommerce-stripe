<?php

namespace Oro\Bundle\StripeBundle\Method\View\Provider;

use Oro\Bundle\PaymentBundle\Method\View\AbstractPaymentMethodViewProvider;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\View\StripePaymentView;

/**
 * Collects all view configuration options for all configured stripe payment methods.
 */
class StripePaymentMethodsViewProvider extends AbstractPaymentMethodViewProvider
{
    private StripePaymentConfigsProvider $configProvider;

    public function __construct(StripePaymentConfigsProvider $configProvider)
    {
        parent::__construct();
        $this->configProvider = $configProvider;
    }

    protected function buildViews(): void
    {
        $paymentConfigs = $this->configProvider->getConfigs();

        /** @var StripePaymentConfig $config */
        foreach ($paymentConfigs as $config) {
            $this->addView($config->getPaymentMethodIdentifier(), new StripePaymentView($config));
        }
    }
}
