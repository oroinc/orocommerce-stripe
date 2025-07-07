<?php

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\View;

use Oro\Bundle\PaymentBundle\Method\View\AbstractPaymentMethodViewProvider;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use Oro\Bundle\StripePaymentBundle\StripeScript\Provider\StripeScriptEnabledProvider;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the Stripe Payment Element payment methods views.
 */
class StripePaymentElementMethodViewProvider extends AbstractPaymentMethodViewProvider
{
    public function __construct(
        private readonly StripePaymentElementConfigProvider $stripePaymentElementConfigProvider,
        private readonly StripeScriptEnabledProvider $stripeScriptEnabledProvider,
        private readonly StripeAmountConverterInterface $stripeAmountConverter,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function buildViews(): void
    {
        $stripePaymentElementConfigs = $this->stripePaymentElementConfigProvider->getPaymentConfigs();

        foreach ($stripePaymentElementConfigs as $stripePaymentElementConfig) {
            $paymentMethodView = new StripePaymentElementMethodView(
                $stripePaymentElementConfig,
                $this->stripeScriptEnabledProvider,
                $this->stripeAmountConverter,
                $this->eventDispatcher
            );

            $this->addView($paymentMethodView->getPaymentMethodIdentifier(), $paymentMethodView);
        }
    }
}
