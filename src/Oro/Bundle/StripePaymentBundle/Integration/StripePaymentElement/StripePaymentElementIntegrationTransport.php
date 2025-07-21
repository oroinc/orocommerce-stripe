<?php

namespace Oro\Bundle\StripePaymentBundle\Integration\StripePaymentElement;

use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\IntegrationBundle\Provider\TransportInterface;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Form\Type\StripePaymentElementSettingsType;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Stripe Payment Element payment method integration transport.
 */
final class StripePaymentElementIntegrationTransport implements TransportInterface
{
    protected ?ParameterBag $settings = null;

    #[\Override]
    public function init(Transport $transportEntity): void
    {
        $this->settings = $transportEntity->getSettingsBag();
    }

    #[\Override]
    public function getLabel(): string
    {
        return 'oro.stripe_payment.payment_element.label';
    }

    #[\Override]
    public function getSettingsFormType(): string
    {
        return StripePaymentElementSettingsType::class;
    }

    #[\Override]
    public function getSettingsEntityFQCN(): string
    {
        return StripePaymentElementSettings::class;
    }
}
