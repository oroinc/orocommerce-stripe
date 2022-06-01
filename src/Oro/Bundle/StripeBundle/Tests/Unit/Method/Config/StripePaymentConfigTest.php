<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Config;

use Oro\Bundle\PaymentBundle\Tests\Unit\Method\Config\AbstractPaymentConfigTestCase;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

class StripePaymentConfigTest extends AbstractPaymentConfigTestCase
{
    protected $config;

    protected function getPaymentConfig(): StripePaymentConfig
    {
        $params = [
            StripePaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test_payment_method_identifier',
            StripePaymentConfig::FIELD_LABEL => 'test label',
            StripePaymentConfig::FIELD_SHORT_LABEL => 'test short label',
            StripePaymentConfig::ADMIN_LABEL => 'admin label',
            StripePaymentConfig::PUBLIC_KEY => 'public key',
            StripePaymentConfig::SECRET_KEY => 'secret key',
            StripePaymentConfig::USER_MONITORING_ENABLED => true,
            StripePaymentConfig::PAYMENT_ACTION => 'manual'
        ];

        return new StripePaymentConfig($params);
    }

    public function testGetAdminLabel(): void
    {
        $this->assertEquals('admin label', $this->config->getAdminLabel());
    }

    public function testGetPublicKey(): void
    {
        $this->assertEquals('public key', $this->config->getPublicKey());
    }

    public function testGetSecretKey(): void
    {
        $this->assertEquals('secret key', $this->config->getSecretKey());
    }

    public function testIsUserMonitoringEnabled(): void
    {
        $this->assertTrue($this->config->isUserMonitoringEnabled());
    }

    public function testGetPaymentAction(): void
    {
        $this->assertEquals('manual', $this->config->getPaymentAction());
    }
}
