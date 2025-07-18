<?php

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement;

use Oro\Bundle\PaymentBundle\Method\Provider\AbstractPaymentMethodProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;

/**
 * Provides the Stripe Payment Element payment methods.
 */
class StripePaymentElementMethodProvider extends AbstractPaymentMethodProvider
{
    /**
     * @param StripePaymentElementConfigProvider $stripePaymentElementConfigProvider
     * @param StripePaymentElementMethodFactory $stripePaymentElementMethodFactory
     * @param array<string> $paymentMethodGroups Payment method groups the payment method applicable for.
     */
    public function __construct(
        private readonly StripePaymentElementConfigProvider $stripePaymentElementConfigProvider,
        private readonly StripePaymentElementMethodFactory $stripePaymentElementMethodFactory,
        private readonly array $paymentMethodGroups
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function collectMethods(): void
    {
        $paymentConfigs = $this->stripePaymentElementConfigProvider->getPaymentConfigs();

        foreach ($paymentConfigs as $stripePaymentElementConfig) {
            $paymentMethod = $this->stripePaymentElementMethodFactory
                ->create($stripePaymentElementConfig, $this->paymentMethodGroups);

            $this->addMethod($paymentMethod->getIdentifier(), $paymentMethod);
        }
    }
}
