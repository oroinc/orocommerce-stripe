<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Integration;

use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Form\Type\StripeSettingsType;
use Oro\Bundle\StripeBundle\Integration\StripeIntegrationTransport;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

class StripeIntegrationTransportTest extends TestCase
{
    private StripeIntegrationTransport $transport;

    #[\Override]
    protected function setUp(): void
    {
        $this->transport = new class() extends StripeIntegrationTransport {
            public function getSettings(): ParameterBag
            {
                return $this->settings;
            }
        };
    }

    public function testInitCompiles(): void
    {
        $settings = new StripeTransportSettings();
        $this->transport->init($settings);
        $this->assertEquals($settings->getSettingsBag(), $this->transport->getSettings());
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('oro.stripe.settings.label', $this->transport->getLabel());
    }

    public function testGetSettingsFormType(): void
    {
        $this->assertEquals(StripeSettingsType::class, $this->transport->getSettingsFormType());
    }

    public function testGetSettingsEntityFQCN(): void
    {
        $this->assertEquals(StripeTransportSettings::class, $this->transport->getSettingsEntityFQCN());
    }
}
