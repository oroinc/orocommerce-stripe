<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Config\Factory;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Generator\IntegrationIdentifierGeneratorInterface;
use Oro\Bundle\LocaleBundle\Entity\LocalizedFallbackValue;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Method\Config\Factory\StripePaymentConfigFactory;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class StripePaymentConfigFactoryTest extends TestCase
{
    use EntityTrait;

    private StripePaymentConfigFactory $factory;

    /** @var LocalizationHelper|\PHPUnit\Framework\MockObject\MockObject  */
    private LocalizationHelper $localizationHelper;

    /** @var IntegrationIdentifierGeneratorInterface|\PHPUnit\Framework\MockObject\MockObject  */
    private IntegrationIdentifierGeneratorInterface $identifierGenerator;

    protected function setUp(): void
    {
        $this->localizationHelper = $this->createMock(LocalizationHelper::class);
        $this->identifierGenerator = $this->createMock(IntegrationIdentifierGeneratorInterface::class);

        $this->factory = new StripePaymentConfigFactory($this->identifierGenerator, $this->localizationHelper);
    }

    public function testCreateConfig(): void
    {
        $channel = $this->getEntity(Channel::class, ['id' => 1, 'name' => 'stripe']);

        $this->identifierGenerator->expects($this->once())
            ->method('generateIdentifier')
            ->with($channel)
            ->willReturn('stripe_1');

        $settingsBag = [
            'channel' => $channel,
            'labels' => [new LocalizedFallbackValue()],
            'shortLabels' => [new LocalizedFallbackValue()],
            'apiPublicKey' => 'public key',
            'apiSecretKey' => 'secret key',
            'paymentAction' => 'manual',
            'userMonitoring' => true,
            'signingSecret' => 'secret'
        ];

        /** @var StripeTransportSettings $settings */
        $settings = $this->getEntity(StripeTransportSettings::class, $settingsBag);

        $this->localizationHelper->expects($this->any())
            ->method('getLocalizedValue')
            ->willReturnMap(
                [
                    [$settings->getLabels(), null, 'test label'],
                    [$settings->getShortLabels(), null, 'test short label'],
                ]
            );

        $config = $this->factory->createConfig($settings);
        $this->assertEquals(new StripePaymentConfig([
            'payment_method_identifier' => 'stripe_1',
            'admin_label' => 'test label',
            'label' => 'test label',
            'short_label' => 'test short label',
            'public_key' => 'public key',
            'secret_key' => 'secret key',
            'payment_action' => 'manual',
            'user_monitoring_enabled' => true,
            'locale' => null,
            'signing_secret' => 'secret'

        ]), $config);
    }
}
