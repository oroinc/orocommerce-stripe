<?php

namespace Oro\Bundle\StripeBundle\Method\Config\Factory;

use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Generator\IntegrationIdentifierGeneratorInterface;
use Oro\Bundle\LocaleBundle\Helper\LocalizationHelper;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Creates config object from Integration settings.
 */
class StripePaymentConfigFactory
{
    private IntegrationIdentifierGeneratorInterface $identifierGenerator;
    private LocalizationHelper $localizationHelper;

    public function __construct(
        IntegrationIdentifierGeneratorInterface $generator,
        LocalizationHelper $localizationHelper
    ) {
        $this->identifierGenerator = $generator;
        $this->localizationHelper = $localizationHelper;
    }

    public function createConfig(StripeTransportSettings $settings): StripePaymentConfig
    {
        $parameters = $settings->getSettingsBag();

        return new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_LABEL =>
                (string)$this->localizationHelper->getLocalizedValue($settings->getLabels()),
            AbstractParameterBagPaymentConfig::FIELD_SHORT_LABEL =>
                (string)$this->localizationHelper->getLocalizedValue($settings->getShortLabels()),
            AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL =>
                (string)$this->localizationHelper->getLocalizedValue($settings->getLabels()),
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER =>
                $this->getPaymentMethodIdentifier($settings->getChannel()),
            StripePaymentConfig::PUBLIC_KEY => $parameters->get(StripeTransportSettings::API_PUBLIC_KEY),
            StripePaymentConfig::SECRET_KEY => $parameters->get(StripeTransportSettings::API_SECRET_KEY),
            StripePaymentConfig::PAYMENT_ACTION => $parameters->get(StripeTransportSettings::PAYMENT_ACTION),
            StripePaymentConfig::USER_MONITORING_ENABLED =>
                (bool)$parameters->get(StripeTransportSettings::USER_MONITORING),
            StripePaymentConfig::LOCALE => $this->getCurrentLocaleCode(),
            StripePaymentConfig::SIGNING_SECRET => $parameters->get(StripeTransportSettings::SIGNING_SECRET),
            StripePaymentConfig::RE_AUTHORIZATION_ERROR_EMAIL => $parameters
                ->get(StripeTransportSettings::RE_AUTHORIZATION_ERROR_EMAIL),
            StripePaymentConfig::ALLOW_RE_AUTHORIZE => (bool)$parameters
                ->get(StripeTransportSettings::ALLOW_RE_AUTHORIZE),
        ]);
    }

    private function getPaymentMethodIdentifier(Channel $channel): string
    {
        return (string)$this->identifierGenerator->generateIdentifier($channel);
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
