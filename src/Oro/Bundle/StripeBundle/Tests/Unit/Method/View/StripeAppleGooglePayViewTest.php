<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\View;

use Oro\Bundle\PaymentBundle\Context\PaymentContext;
use Oro\Bundle\StripeBundle\Method\View\StripeAppleGooglePayView;

class StripeAppleGooglePayViewTest extends StripePaymentViewTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->view = new StripeAppleGooglePayView($this->config);
    }

    public function testGetBlock(): void
    {
        $this->assertEquals('_payment_methods_stripe_apple_google_pay_widget', $this->view->getBlock());
    }

    public function testGetLabel(): void
    {
        $this->assertEquals('Apple Pay/Google Pay', $this->view->getLabel());
    }

    public function testGetAdminLabel(): void
    {
        $this->assertEquals('adminlabel Apple Pay/Google Pay', $this->view->getAdminLabel());
    }

    public function testGetShortLabel(): void
    {
        $this->assertEquals('Apple Pay/Google Pay', $this->view->getShortLabel());
    }

    public function testGetPaymentMethodIdentifier(): void
    {
        $this->assertEquals('test_apple_google_pay', $this->view->getPaymentMethodIdentifier());
    }

    public function testGetOptions(): void
    {
        $expected = [
            'componentOptions' => [
                'publicKey' => 'key',
                'isUserMonitoringEnabled' => true,
                'locale' => null,
                'apiVersion' => '2022-11-15'
            ],
            'cssClass' => 'hidden stripe-apple-google-pay-method-container',
        ];

        $this->assertEquals($expected, $this->view->getOptions(new PaymentContext([])));
    }
}
