<?php

namespace Oro\Bundle\StripeBundle\Method\Config\Provider;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Integration\StripeChannelType;
use Oro\Bundle\StripeBundle\Method\Config\Factory\StripePaymentConfigFactory;

/**
 * Collect all configs from configured available Stripe integrations.
 */
class StripePaymentConfigsProvider
{
    private ManagerRegistry $managerRegistry;
    private StripePaymentConfigFactory $configFactory;
    private ?array $configs = null;

    public function __construct(ManagerRegistry $managerRegistry, StripePaymentConfigFactory $configFactory)
    {
        $this->managerRegistry = $managerRegistry;
        $this->configFactory = $configFactory;
    }

    public function getConfigs(): ?array
    {
        if (null === $this->configs) {
            $this->collectConfigs();
        }

        return $this->configs;
    }

    /**
     * Capture settings from configured integration.
     */
    private function collectConfigs(): void
    {
        $integrations = $this->managerRegistry->getRepository(StripeTransportSettings::class)
            ->getEnabledSettingsByType(StripeChannelType::TYPE);

        if (count($integrations)) {
            foreach ($integrations as $settings) {
                $config = $this->configFactory->createConfig($settings);
                $this->configs[$config->getPaymentMethodIdentifier()] = $config;
            }
        } else {
            $this->configs = [];
        }
    }
}
