<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\View;

use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\View\StripePaymentView;
use PHPUnit\Framework\TestCase;

class StripePaymentViewTest extends TestCase
{
    protected StripePaymentConfig $config;
    protected StripePaymentView $view;

    #[\Override]
    protected function setUp(): void
    {
        $this->config = new StripePaymentConfig([
            StripePaymentConfig::PUBLIC_KEY => 'key',
            StripePaymentConfig::USER_MONITORING_ENABLED => true,
            StripePaymentConfig::ADMIN_LABEL => 'adminlabel',
            StripePaymentConfig::FIELD_SHORT_LABEL => 'shortlabel',
            StripePaymentConfig::FIELD_LABEL => 'label',
            StripePaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);
        $this->view = new StripePaymentView($this->config);
    }

    public function testGetOptions(): void
    {
        $expected = [
            'componentOptions' => [
                'publicKey' => 'key',
                'isUserMonitoringEnabled' => true,
                'locale' => null,
                'apiVersion' => '2022-11-15'
            ]
        ];

        $this->assertEquals($expected, $this->view->getOptions(new PaymentContext([])));
    }

    public function testGetBlock(): void
    {
        $this->assertEquals('_payment_methods_stripe_payment_widget', $this->view->getBlock());
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('label', $this->view->getLabel());
    }

    public function testGetAdminLabel(): void
    {
        $this->assertEquals('adminlabel', $this->view->getAdminLabel());
    }

    public function testGetShortLabel(): void
    {
        $this->assertEquals('shortlabel', $this->view->getShortLabel());
    }

    public function testGetPaymentMethodIdentifier(): void
    {
        $this->assertEquals('test', $this->view->getPaymentMethodIdentifier());
    }
}
