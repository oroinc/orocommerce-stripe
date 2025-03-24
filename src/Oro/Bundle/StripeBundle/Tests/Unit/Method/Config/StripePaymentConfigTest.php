<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Config;

use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class StripePaymentConfigTest extends TestCase
{
    private StripePaymentConfig $config;

    #[\Override]
    protected function setUp(): void
    {
        $this->config = new StripePaymentConfig([
            StripePaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test_payment_method_identifier',
            StripePaymentConfig::FIELD_LABEL => 'test label',
            StripePaymentConfig::FIELD_SHORT_LABEL => 'test short label',
            StripePaymentConfig::ADMIN_LABEL => 'test admin label',
            StripePaymentConfig::APPLE_GOOGLE_PAY_LABEL => 'apple_google_pay_label',
            StripePaymentConfig::PUBLIC_KEY => 'public key',
            StripePaymentConfig::SECRET_KEY => 'secret key',
            StripePaymentConfig::USER_MONITORING_ENABLED => true,
            StripePaymentConfig::PAYMENT_ACTION => 'manual',
            StripePaymentConfig::ALLOW_RE_AUTHORIZE => true,
            StripePaymentConfig::RE_AUTHORIZATION_ERROR_EMAIL => ['test@test.com']
        ]);
    }

    public function testGetLabel(): void
    {
        self::assertSame('test label', $this->config->getLabel());
    }

    public function testGetShortLabel(): void
    {
        self::assertSame('test short label', $this->config->getShortLabel());
    }

    public function testGetAdminLabel(): void
    {
        self::assertSame('test admin label', $this->config->getAdminLabel());
    }

    public function testGetPaymentMethodIdentifier(): void
    {
        self::assertSame('test_payment_method_identifier', $this->config->getPaymentMethodIdentifier());
    }

    public function testGetGetAppleGooglePayLabel(): void
    {
        self::assertEquals('apple_google_pay_label', $this->config->getAppleGooglePayLabel());
    }

    public function testGetPublicKey(): void
    {
        self::assertEquals('public key', $this->config->getPublicKey());
    }

    public function testGetSecretKey(): void
    {
        self::assertEquals('secret key', $this->config->getSecretKey());
    }

    public function testIsUserMonitoringEnabled(): void
    {
        self::assertTrue($this->config->isUserMonitoringEnabled());
    }

    public function testGetPaymentAction(): void
    {
        self::assertEquals('manual', $this->config->getPaymentAction());
    }

    public function testIsReAuthorizationAllowed(): void
    {
        self::assertTrue($this->config->isReAuthorizationAllowed());
    }

    public function testGetReAuthorizationErrorEmail(): void
    {
        self::assertEquals(['test@test.com'], $this->config->getReAuthorizationErrorEmail());
    }
}
