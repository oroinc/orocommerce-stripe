<?php

namespace Oro\Bundle\StripeBundle\Method\Provider;

use Oro\Bundle\PaymentBundle\Method\Provider\AbstractPaymentMethodProvider;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\Factory\StripePaymentMethodFactory;

/**
 * Collects all payment methods configured with Stripe integration.
 */
class StripePaymentMethodsProvider extends AbstractPaymentMethodProvider
{
    private StripePaymentConfigsProvider $paymentsConfigProvider;
    private StripePaymentMethodFactory $paymentMethodFactory;

    public function __construct(
        StripePaymentConfigsProvider $paymentsConfigProvider,
        StripePaymentMethodFactory $paymentMethodFactory
    ) {
        parent::__construct();
        $this->paymentsConfigProvider = $paymentsConfigProvider;
        $this->paymentMethodFactory = $paymentMethodFactory;
    }

    protected function collectMethods(): void
    {
        $paymentConfigs = $this->paymentsConfigProvider->getConfigs();

        /** @var StripePaymentConfig $config */
        foreach ($paymentConfigs as $config) {
            $this->addMethod(
                $config->getPaymentMethodIdentifier(),
                $this->paymentMethodFactory->create($config)
            );
        }
    }
}
