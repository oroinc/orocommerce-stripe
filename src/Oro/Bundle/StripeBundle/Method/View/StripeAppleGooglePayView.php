<?php

namespace Oro\Bundle\StripeBundle\Method\View;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\StripeBundle\Method\StripeAppleGooglePaymentMethod;

/**
 * Provides view options specific to Apple/Google Pay payment method
 */
class StripeAppleGooglePayView extends StripePaymentView
{
    public function getLabel(): string
    {
        return $this->getAppleGooglePayLabel();
    }

    public function getAdminLabel(): string
    {
        return sprintf('%s %s', parent::getAdminLabel(), $this->getAppleGooglePayLabel());
    }

    public function getShortLabel(): string
    {
        return $this->getAppleGooglePayLabel();
    }

    public function getPaymentMethodIdentifier(): string
    {
        return StripeAppleGooglePaymentMethod::buildIdentifier(parent::getPaymentMethodIdentifier());
    }

    public function getBlock(): string
    {
        return '_payment_methods_stripe_apple_google_pay_widget';
    }

    protected function getAppleGooglePayLabel(): string
    {
        return $this->config->getAppleGooglePayLabel();
    }

    public function getOptions(PaymentContextInterface $context): array
    {
        return array_merge(
            parent::getOptions($context),
            [
                'cssClass' => 'hidden stripe-apple-google-pay-method-container',
            ]
        );
    }
}
