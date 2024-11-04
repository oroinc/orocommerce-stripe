<?php

namespace Oro\Bundle\StripeBundle\Integration;

use Oro\Bundle\IntegrationBundle\Entity\Transport;
use Oro\Bundle\IntegrationBundle\Provider\TransportInterface;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Form\Type\StripeSettingsType;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Basic Stripe integration transport configuration.
 */
class StripeIntegrationTransport implements TransportInterface
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
        return 'oro.stripe.settings.label';
    }

    #[\Override]
    public function getSettingsFormType(): string
    {
        return StripeSettingsType::class;
    }

    #[\Override]
    public function getSettingsEntityFQCN(): string
    {
        return StripeTransportSettings::class;
    }
}
