<?php

namespace Oro\Bundle\StripeBundle\Method\Config;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;

/**
 * Stores configuration data used in integration with Stripe payment system.
 */
class StripePaymentConfig extends AbstractParameterBagPaymentConfig
{
    public const ADMIN_LABEL = 'admin_label';
    public const PUBLIC_KEY = 'public_key';
    public const SECRET_KEY = 'secret_key';
    public const USER_MONITORING_ENABLED = 'user_monitoring_enabled';
    public const PAYMENT_ACTION = 'payment_action';
    public const LOCALE = 'locale';
    public const SIGNING_SECRET = 'signing_secret';
    public const SUPPORT_PARTIAL_CAPTURE = 'support_partial_capture';
    public const ALLOW_RE_AUTHORIZE = 'enable_re_authorize';
    public const RE_AUTHORIZATION_ERROR_EMAIL = 're_authorization_error_email';

    public function getAdminLabel(): ?string
    {
        return $this->get(self::ADMIN_LABEL);
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
