<?php

namespace Oro\Bundle\StripeBundle\Method\View;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Method\View\PaymentMethodViewInterface;
use Oro\Bundle\StripeBundle\Client\StripeGateway;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Implements logic to provide view options needed for integration with Stripe.
 */
class StripePaymentView implements PaymentMethodViewInterface
{
    protected StripePaymentConfig $config;

    public function __construct(StripePaymentConfig $config)
    {
        $this->config = $config;
    }

    #[\Override]
    public function getOptions(PaymentContextInterface $context): array
    {
        return [
            'componentOptions' => [
                'publicKey' => $this->config->getPublicKey(),
                'isUserMonitoringEnabled' => $this->config->isUserMonitoringEnabled(),
                'locale' => $this->config->getLocale(),
                'apiVersion' => StripeGateway::API_VERSION
            ]
        ];
    }

    #[\Override]
    public function getBlock(): string
    {
        return '_payment_methods_stripe_payment_widget';
    }

    #[\Override]
    public function getLabel(): string
    {
        return $this->config->getLabel();
    }

    #[\Override]
    public function getAdminLabel(): string
    {
        return $this->config->getAdminLabel();
    }

    #[\Override]
    public function getShortLabel(): string
    {
        return $this->config->getShortLabel();
    }

    #[\Override]
    public function getPaymentMethodIdentifier(): string
    {
        return $this->config->getPaymentMethodIdentifier();
    }
}
