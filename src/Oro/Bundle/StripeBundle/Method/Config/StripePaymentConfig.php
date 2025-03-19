<?php

namespace Oro\Bundle\StripeBundle\Method\Config;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripeBundle\Entity\StripeTransportSettings;

/**
 * Stores configuration data used in integration with Stripe payment system.
 */
class StripePaymentConfig extends AbstractParameterBagPaymentConfig
{
    public const string ADMIN_LABEL = 'admin_label';
    public const string APPLE_GOOGLE_PAY_LABEL = 'apple_google_pay_label';
    public const string PUBLIC_KEY = 'public_key';
    public const string SECRET_KEY = 'secret_key';
    public const string USER_MONITORING_ENABLED = 'user_monitoring_enabled';
    public const string PAYMENT_ACTION = 'payment_action';
    public const string LOCALE = 'locale';
    public const string SIGNING_SECRET = 'signing_secret';
    public const string SUPPORT_PARTIAL_CAPTURE = 'support_partial_capture';
    public const string ALLOW_RE_AUTHORIZE = 'enable_re_authorize';
    public const string RE_AUTHORIZATION_ERROR_EMAIL = 're_authorization_error_email';

    #[\Override]
    public function getAdminLabel(): ?string
    {
        return $this->get(self::ADMIN_LABEL);
    }

    public function getAppleGooglePayLabel(): ?string
    {
        return $this->get(self::APPLE_GOOGLE_PAY_LABEL) ?: StripeTransportSettings::DEFAULT_APPLE_GOOGLE_PAY_LABEL;
    }

    public function getPublicKey(): ?string
    {
        return $this->get(self::PUBLIC_KEY);
    }

    public function getSecretKey(): ?string
    {
        return $this->get(self::SECRET_KEY);
    }

    public function isUserMonitoringEnabled(): bool
    {
        return $this->get(self::USER_MONITORING_ENABLED);
    }

    public function getPaymentAction(): string
    {
        return $this->get(self::PAYMENT_ACTION);
    }

    public function getLocale(): ?string
    {
        return $this->get(self::LOCALE);
    }

    public function getSigningSecret(): string
    {
        return $this->get(self::SIGNING_SECRET);
    }

    public function isPartialCaptureSupports()
    {
        return $this->get(self::SUPPORT_PARTIAL_CAPTURE);
    }

    public function isReAuthorizationAllowed(): bool
    {
        return $this->get(self::ALLOW_RE_AUTHORIZE);
    }

    public function getReAuthorizationErrorEmail(): array
    {
        return $this->get(self::RE_AUTHORIZATION_ERROR_EMAIL);
    }
}
