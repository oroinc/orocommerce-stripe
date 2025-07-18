<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config;

use Oro\Bundle\IntegrationBundle\Generator\IntegrationIdentifierGeneratorInterface;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripePaymentBundle\Configuration\StripePaymentConfiguration;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Creates a payment method config for the Stripe Payment Element payment method.
 */
class StripePaymentElementConfigFactory
{
    public function __construct(
        private readonly IntegrationIdentifierGeneratorInterface $integrationIdentifierGenerator,
        private readonly LocalizationHelper $localizationHelper,
        private readonly StripePaymentConfiguration $stripePaymentConfiguration,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $stripeApiVersion,
        private readonly string $stripeJsVersion,
        private readonly string $webhookRoute,
        private readonly string $reAuthorizationEmailTemplateName
    ) {
    }

    public function createConfig(StripePaymentElementSettings $settings): StripePaymentElementConfig
    {
        $manualCapturePaymentMethodTypes = $this->stripePaymentConfiguration->getPaymentMethodTypesWithManualCapture();

        return new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL => $settings->getPaymentMethodName(),
            AbstractParameterBagPaymentConfig::FIELD_LABEL => $this->getPaymentMethodLabel($settings),
            AbstractParameterBagPaymentConfig::FIELD_SHORT_LABEL => $this->getPaymentMethodShortLabel($settings),
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => $this->generateIdentifier($settings),
            StripePaymentElementConfig::API_VERSION => $this->stripeApiVersion,
            StripePaymentElementConfig::API_PUBLIC_KEY => $settings->getApiPublicKey(),
            StripePaymentElementConfig::API_SECRET_KEY => $settings->getApiSecretKey(),
            StripePaymentElementConfig::SCRIPT_VERSION => $this->stripeJsVersion,
            StripePaymentElementConfig::WEBHOOK_URL => $this->generateWebhookUrl($settings),
            StripePaymentElementConfig::WEBHOOK_ACCESS_ID => $settings->getWebhookAccessId(),
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => $settings->getWebhookStripeId(),
            StripePaymentElementConfig::WEBHOOK_SECRET => $settings->getWebhookSecret(),
            StripePaymentElementConfig::CAPTURE_METHOD => $settings->getCaptureMethod(),
            StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => $manualCapturePaymentMethodTypes,
            StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED => $settings->isReAuthorizationEnabled(),
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL => $this->getReAuthorizationEmails($settings),
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL_TEMPLATE => $this->reAuthorizationEmailTemplateName,
            StripePaymentElementConfig::USER_MONITORING_ENABLED => $settings->isUserMonitoringEnabled(),
            StripePaymentElementConfig::LOCALE => $this->getCurrentLocaleCode(),
        ]);
    }

    private function generateIdentifier(StripePaymentElementSettings $stripePaymentElementSettings): ?string
    {
        if ($stripePaymentElementSettings->getChannel() === null) {
            return null;
        }

        return $this->integrationIdentifierGenerator->generateIdentifier($stripePaymentElementSettings->getChannel());
    }

    /**
     * @param StripePaymentElementSettings $stripePaymentElementSettings
     *
     * @return array<string>
     */
    private function getReAuthorizationEmails(StripePaymentElementSettings $stripePaymentElementSettings): array
    {
        $notificationEmails = explode(',', (string)$stripePaymentElementSettings->getReAuthorizationEmail());

        return array_map('trim', array_filter($notificationEmails));
    }

    private function getPaymentMethodLabel(StripePaymentElementSettings $stripePaymentElementSettings): string
    {
        return (string)$this->localizationHelper->getLocalizedValue(
            $stripePaymentElementSettings->getPaymentMethodLabels()
        );
    }

    private function getPaymentMethodShortLabel(StripePaymentElementSettings $stripePaymentElementSettings): string
    {
        return (string)$this->localizationHelper->getLocalizedValue(
            $stripePaymentElementSettings->getPaymentMethodShortLabels()
        );
    }

    private function generateWebhookUrl(StripePaymentElementSettings $stripePaymentElementSettings): string
    {
        return $this->urlGenerator->generate(
            $this->webhookRoute,
            ['webhookAccessId' => $stripePaymentElementSettings->getWebhookAccessId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    private function getCurrentLocaleCode(): ?string
    {
        $languageCode = $this->localizationHelper->getCurrentLocalization()?->getLanguageCode();
        if (!$languageCode) {
            return null;
        }

        $code = explode('_', $languageCode);

        return $code ? reset($code) : null;
    }
}
