<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config;

use Oro\Bundle\StripePaymentBundle\Entity\Repository\StripePaymentElementSettingsRepository;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Config\StripeReAuthorizationConfigInterface;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Config\StripeReAuthorizationConfigProviderInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEvent\Config\StripeWebhookEndpointConfigProviderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Provides payment method configs for the Stripe Payment Element payment method.
 */
class StripePaymentElementConfigProvider implements
    StripeReAuthorizationConfigProviderInterface,
    StripeWebhookEndpointConfigProviderInterface,
    ResetInterface
{
    /**
     * @var array<string,StripePaymentElementConfig>|null
     */
    private ?array $paymentConfigs = null;

    public function __construct(
        private readonly StripePaymentElementSettingsRepository $stripePaymentElementSettingsRepository,
        private readonly StripePaymentElementConfigFactory $stripePaymentElementConfigFactory
    ) {
    }

    /**
     * @return array<string,StripePaymentElementConfig>
     */
    public function getPaymentConfigs(): array
    {
        $this->paymentConfigs ??= $this->doGetPaymentConfigs();

        return (array)$this->paymentConfigs;
    }

    public function getPaymentConfig(string $paymentMethodIdentifier): ?StripePaymentElementConfig
    {
        $this->paymentConfigs ??= $this->doGetPaymentConfigs();

        return $this->paymentConfigs[$paymentMethodIdentifier] ?? null;
    }

    private function doGetPaymentConfigs(): array
    {
        $enabledSettings = $this->stripePaymentElementSettingsRepository->findEnabledSettings();
        $paymentConfigs = [];
        foreach ($enabledSettings as $stripePaymentElementSettings) {
            $paymentConfig = $this->stripePaymentElementConfigFactory->createConfig($stripePaymentElementSettings);
            $paymentConfigs[$paymentConfig->getPaymentMethodIdentifier()] = $paymentConfig;
        }

        return $paymentConfigs;
    }

    #[\Override]
    public function getReAuthorizationConfig(
        string $paymentMethodIdentifier
    ): ?StripeReAuthorizationConfigInterface {
        $this->paymentConfigs ??= $this->doGetPaymentConfigs();

        return $this->paymentConfigs[$paymentMethodIdentifier] ?? null;
    }

    #[\Override]
    public function getStripeWebhookEndpointConfig(string $webhookAccessId): ?StripeWebhookEndpointConfigInterface
    {
        $this->paymentConfigs ??= $this->doGetPaymentConfigs();

        foreach ($this->paymentConfigs as $paymentConfig) {
            if ($paymentConfig->getWebhookAccessId() === $webhookAccessId) {
                return $paymentConfig;
            }
        }

        return null;
    }

    #[\Override]
    public function reset(): void
    {
        $this->paymentConfigs = null;
    }
}
