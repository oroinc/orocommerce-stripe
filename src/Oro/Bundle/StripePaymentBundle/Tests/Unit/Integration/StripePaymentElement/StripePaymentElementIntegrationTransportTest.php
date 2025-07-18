<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Integration\StripePaymentElement;

use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\Form\Type\StripePaymentElementSettingsType;
use Oro\Bundle\StripePaymentBundle\Integration\StripePaymentElement\StripePaymentElementIntegrationTransport;
use PHPUnit\Framework\TestCase;

final class StripePaymentElementIntegrationTransportTest extends TestCase
{
    private StripePaymentElementIntegrationTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new StripePaymentElementIntegrationTransport();
    }

    public function testGetLabel(): void
    {
        self::assertSame(
            'oro.stripe_payment.payment_element.label',
            $this->transport->getLabel()
        );
    }

    public function testGetSettingsFormType(): void
    {
        self::assertSame(
            StripePaymentElementSettingsType::class,
            $this->transport->getSettingsFormType()
        );
    }

    public function testGetSettingsEntityFQCN(): void
    {
        self::assertSame(
            StripePaymentElementSettings::class,
            $this->transport->getSettingsEntityFQCN()
        );
    }
}
