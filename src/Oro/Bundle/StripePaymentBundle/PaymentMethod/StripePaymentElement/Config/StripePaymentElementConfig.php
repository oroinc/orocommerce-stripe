<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config;

use Oro\Bundle\LocaleBundle\DependencyInjection\Configuration;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Config\StripeReAuthorizationConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeScript\StripeScriptConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointConfigInterface;

/**
 * Represents the Stripe Payment Element payment method config.
 */
class StripePaymentElementConfig extends AbstractParameterBagPaymentConfig implements
    StripeClientConfigInterface,
    StripePaymentIntentConfigInterface,
    StripeReAuthorizationConfigInterface,
    StripeScriptConfigInterface,
    StripeWebhookEndpointConfigInterface
{
    public const string API_VERSION = 'api_version';
    public const string API_PUBLIC_KEY = 'api_public_key';
    public const string API_SECRET_KEY = 'api_secret_key';
    public const string SCRIPT_VERSION = 'script_version';
    public const string WEBHOOK_URL = 'webhook_route';
    public const string WEBHOOK_ACCESS_ID = 'webhook_access_id';
    public const string WEBHOOK_STRIPE_ID = 'webhook_stripe_id';
    public const string WEBHOOK_SECRET = 'webhook_secret';
    public const string CAPTURE_METHOD = 'capture_method';
    public const string MANUAL_CAPTURE_PAYMENT_METHOD_TYPES = 'manual_capture_payment_method_types';
    public const string RE_AUTHORIZATION_ENABLED = 're_authorization_enabled';
    public const string RE_AUTHORIZATION_EMAIL = 're_authorization_email';
    public const string RE_AUTHORIZATION_EMAIL_TEMPLATE = 're_authorization_email_template';
    public const string USER_MONITORING_ENABLED = 'user_monitoring_enabled';
    public const string LOCALE = 'locale';

    #[\Override]
    public function getApiVersion(): string
    {
        return (string)$this->get(self::API_VERSION);
    }

    #[\Override]
    public function getApiPublicKey(): string
    {
        return (string)$this->get(self::API_PUBLIC_KEY);
    }

    #[\Override]
    public function getApiSecretKey(): string
    {
        return (string)$this->get(self::API_SECRET_KEY);
    }

    #[\Override]
    public function getScriptVersion(): string
    {
        return (string)$this->get(self::SCRIPT_VERSION);
    }

    #[\Override]
    public function getStripeClientConfig(): array
    {
        return [
            'stripe_version' => $this->getApiVersion(),
            'api_key' => $this->getApiSecretKey(),
        ];
    }

    #[\Override]
    public function getCaptureMethod(): string
    {
        return (string)$this->get(self::CAPTURE_METHOD);
    }

    #[\Override]
    public function getPaymentMethodTypesWithManualCapture(): array
    {
        return (array)$this->get(self::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES);
    }

    #[\Override]
    public function getWebhookUrl(): string
    {
        return (string)$this->get(self::WEBHOOK_URL);
    }

    #[\Override]
    public function getWebhookAccessId(): string
    {
        return (string)$this->get(self::WEBHOOK_ACCESS_ID);
    }

    #[\Override]
    public function getWebhookStripeId(): string
    {
        return (string)$this->get(self::WEBHOOK_STRIPE_ID);
    }

    #[\Override]
    public function getWebhookSecret(): string
    {
        return (string)$this->get(self::WEBHOOK_SECRET);
    }

    #[\Override]
    public function getWebhookDescription(): string
    {
        return sprintf('OroCommerce Webhook %s', $this->getAdminLabel());
    }

    #[\Override]
    public function getWebhookEvents(): array
    {
        return [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'refund.updated',
        ];
    }

    #[\Override]
    public function getWebhookMetadata(): array
    {
        return [
            'payment_method_name' => $this->getAdminLabel(),
        ];
    }

    #[\Override]
    public function isReAuthorizationEnabled(): bool
    {
        return $this->getCaptureMethod() === 'manual' && $this->get(self::RE_AUTHORIZATION_ENABLED);
    }

    #[\Override]
    public function getReAuthorizationEmail(): array
    {
        return (array)$this->get(self::RE_AUTHORIZATION_EMAIL);
    }

    #[\Override]
    public function getReAuthorizationEmailTemplate(): string
    {
        return (string)$this->get(self::RE_AUTHORIZATION_EMAIL_TEMPLATE);
    }

    #[\Override]
    public function isUserMonitoringEnabled(): bool
    {
        return (bool)$this->get(self::USER_MONITORING_ENABLED);
    }

    #[\Override]
    public function getLocale(): string
    {
        return $this->get(self::LOCALE) ?? Configuration::DEFAULT_LOCALE;
    }
}
