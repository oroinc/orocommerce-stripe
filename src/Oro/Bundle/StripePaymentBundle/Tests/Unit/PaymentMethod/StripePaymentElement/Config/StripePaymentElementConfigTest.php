<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement\Config;

use Oro\Bundle\LocaleBundle\DependencyInjection\Configuration;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeScript\StripeScriptConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointConfigInterface;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.TooManyMethods)
 */
final class StripePaymentElementConfigTest extends TestCase
{
    private array $testConfig = [
        AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_payment_element_11',
        AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL => 'Stripe Payment',
        AbstractParameterBagPaymentConfig::FIELD_LABEL => 'Stripe Payment Element',
        AbstractParameterBagPaymentConfig::FIELD_SHORT_LABEL => 'Stripe',
        StripePaymentElementConfig::API_VERSION => '2025-02-24.acacia',
        StripePaymentElementConfig::API_PUBLIC_KEY => 'pk_test_123',
        StripePaymentElementConfig::API_SECRET_KEY => 'sk_test_123',
        StripePaymentElementConfig::SCRIPT_VERSION => 'basil',
        StripePaymentElementConfig::WEBHOOK_URL => 'oro_stripe_payment_webhook_payment_element',
        StripePaymentElementConfig::WEBHOOK_ACCESS_ID => 'wh_test123',
        StripePaymentElementConfig::WEBHOOK_STRIPE_ID => 'we_test123',
        StripePaymentElementConfig::WEBHOOK_SECRET => 'whsec_test123',
        StripePaymentElementConfig::CAPTURE_METHOD => 'manual',
        StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES => ['cards', 'amazon_pay'],
        StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED => true,
        StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL => ['admin@example.com'],
        StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL_TEMPLATE =>
            'stripe_payment_element_re_authorization_failure',
        StripePaymentElementConfig::USER_MONITORING_ENABLED => false,
        StripePaymentElementConfig::LOCALE => 'en',
    ];

    public function testImplementsRequiredInterfaces(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertInstanceOf(AbstractParameterBagPaymentConfig::class, $config);
        self::assertInstanceOf(StripeClientConfigInterface::class, $config);
        self::assertInstanceOf(StripePaymentIntentConfigInterface::class, $config);
        self::assertInstanceOf(StripeScriptConfigInterface::class, $config);
        self::assertInstanceOf(StripeWebhookEndpointConfigInterface::class, $config);
    }

    public function testGetPaymentMethodIdentifierLabel(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame(
            $this->testConfig[AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER],
            $config->getPaymentMethodIdentifier()
        );
    }

    public function testGetAdminLabel(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame(
            $this->testConfig[AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL],
            $config->getAdminLabel()
        );
    }

    public function testGetLabel(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);
        self::assertSame(
            $this->testConfig[AbstractParameterBagPaymentConfig::FIELD_LABEL],
            $config->getLabel()
        );
    }


    public function testGetShortLabel(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame(
            $this->testConfig[AbstractParameterBagPaymentConfig::FIELD_SHORT_LABEL],
            $config->getShortLabel()
        );
    }

    public function testGetApiVersion(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);
        self::assertSame($this->testConfig[StripePaymentElementConfig::API_VERSION], $config->getApiVersion());

        // Test with empty value
        $testConfig = $this->testConfig;
        unset($testConfig[StripePaymentElementConfig::API_VERSION]);
        $config = new StripePaymentElementConfig($testConfig);
        self::assertSame('', $config->getApiVersion());
    }

    public function testGetApiPublicKey(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame($this->testConfig[StripePaymentElementConfig::API_PUBLIC_KEY], $config->getApiPublicKey());
    }

    public function testGetApiSecretKey(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame($this->testConfig[StripePaymentElementConfig::API_SECRET_KEY], $config->getApiSecretKey());
    }

    public function testGetScriptVersion(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame($this->testConfig[StripePaymentElementConfig::SCRIPT_VERSION], $config->getScriptVersion());
    }

    public function testGetStripeClientConfig(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        $expected = [
            'stripe_version' => $this->testConfig[StripePaymentElementConfig::API_VERSION],
            'api_key' => $this->testConfig[StripePaymentElementConfig::API_SECRET_KEY],
        ];
        self::assertSame($expected, $config->getStripeClientConfig());
    }

    public function testGetCaptureMethod(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame($this->testConfig[StripePaymentElementConfig::CAPTURE_METHOD], $config->getCaptureMethod());
    }

    public function testGetPaymentMethodTypesWithManualCapture(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame(
            $this->testConfig[StripePaymentElementConfig::MANUAL_CAPTURE_PAYMENT_METHOD_TYPES],
            $config->getPaymentMethodTypesWithManualCapture()
        );
    }

    public function testGetWebhookRoute(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame($this->testConfig[StripePaymentElementConfig::WEBHOOK_URL], $config->getWebhookUrl());
    }

    public function testGetWebhookAccessId(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame(
            $this->testConfig[StripePaymentElementConfig::WEBHOOK_ACCESS_ID],
            $config->getWebhookAccessId()
        );
    }

    public function testGetWebhookStripeId(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame(
            $this->testConfig[StripePaymentElementConfig::WEBHOOK_STRIPE_ID],
            $config->getWebhookStripeId()
        );
    }

    public function testGetWebhookSecret(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame($this->testConfig[StripePaymentElementConfig::WEBHOOK_SECRET], $config->getWebhookSecret());
    }

    public function testGetWebhookDescription(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertSame('OroCommerce Webhook Stripe Payment', $config->getWebhookDescription());
    }

    public function testGetWebhookEvents(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        $expected = [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
            'refund.updated',
        ];
        self::assertSame($expected, $config->getWebhookEvents());
    }

    public function testGetWebhookMetadata(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        $expected = [
            'payment_method_name' => $this->testConfig[AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL],
        ];
        self::assertSame($expected, $config->getWebhookMetadata());
    }

    public function testIsReAuthorizationEnabled(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);

        self::assertTrue($config->isReAuthorizationEnabled());

        // Test with false value
        $testConfig = $this->testConfig;
        $testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED] = false;
        $config = new StripePaymentElementConfig($testConfig);

        self::assertFalse($config->isReAuthorizationEnabled());
    }

    public function testGetReAuthorizationEmail(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);
        self::assertSame(
            $this->testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL],
            $config->getReAuthorizationEmail()
        );

        // Test with empty value
        $testConfig = $this->testConfig;
        $testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL] = [];
        $config = new StripePaymentElementConfig($testConfig);
        self::assertSame([], $config->getReAuthorizationEmail());
    }

    public function testGetReAuthorizationEmailTemplate(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);
        self::assertSame(
            $this->testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL_TEMPLATE],
            $config->getReAuthorizationEmailTemplate()
        );

        // Test with empty value
        $testConfig = $this->testConfig;
        $testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL] = [];
        $config = new StripePaymentElementConfig($testConfig);
        self::assertSame([], $config->getReAuthorizationEmail());
    }

    public function testIsUserMonitoringEnabled(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);
        self::assertFalse($config->isUserMonitoringEnabled());

        // Test with true value
        $testConfig = $this->testConfig;
        $testConfig[StripePaymentElementConfig::USER_MONITORING_ENABLED] = true;
        $config = new StripePaymentElementConfig($testConfig);
        self::assertTrue($config->isUserMonitoringEnabled());
    }

    public function testGetLocale(): void
    {
        $config = new StripePaymentElementConfig($this->testConfig);
        self::assertSame($this->testConfig[StripePaymentElementConfig::LOCALE], $config->getLocale());

        // Test with default value
        $testConfig = $this->testConfig;
        unset($testConfig[StripePaymentElementConfig::LOCALE]);
        $config = new StripePaymentElementConfig($testConfig);
        self::assertSame(Configuration::DEFAULT_LOCALE, $config->getLocale());
    }

    public function testEmptyConfigValues(): void
    {
        $config = new StripePaymentElementConfig([]);

        self::assertNull($config->getPaymentMethodIdentifier());
        self::assertNull($config->getAdminLabel());
        self::assertNull($config->getLabel());
        self::assertNull($config->getShortLabel());
        self::assertSame('', $config->getApiVersion());
        self::assertSame('', $config->getApiPublicKey());
        self::assertSame('', $config->getApiSecretKey());
        self::assertSame('', $config->getScriptVersion());
        self::assertSame('', $config->getCaptureMethod());
        self::assertSame([], $config->getPaymentMethodTypesWithManualCapture());
        self::assertSame('', $config->getWebhookUrl());
        self::assertSame('', $config->getWebhookAccessId());
        self::assertSame('', $config->getWebhookStripeId());
        self::assertSame('', $config->getWebhookSecret());
        self::assertFalse($config->isReAuthorizationEnabled());
        self::assertSame([], $config->getReAuthorizationEmail());
        self::assertSame('', $config->getReAuthorizationEmailTemplate());
        self::assertFalse($config->isUserMonitoringEnabled());
        self::assertSame(Configuration::DEFAULT_LOCALE, $config->getLocale());
    }

    public function testTypeCasting(): void
    {
        $testConfig = $this->testConfig;
        $testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_ENABLED] = '1';
        $testConfig[StripePaymentElementConfig::USER_MONITORING_ENABLED] = '0';
        $testConfig[StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL] = 'single@example.com';

        $config = new StripePaymentElementConfig($testConfig);

        self::assertTrue($config->isReAuthorizationEnabled());
        self::assertFalse($config->isUserMonitoringEnabled());
        self::assertSame(['single@example.com'], $config->getReAuthorizationEmail());
    }
}
