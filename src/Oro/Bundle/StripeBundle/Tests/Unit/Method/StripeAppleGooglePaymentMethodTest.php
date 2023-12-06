<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\StripeAppleGooglePaymentMethod;

class StripeAppleGooglePaymentMethodTest extends StripePaymentMethodTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $config = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'test'
        ]);

        $this->method = new StripeAppleGooglePaymentMethod($config, $this->registry);
    }

    public function testGetIdentifier(): void
    {
        $this->assertEquals('test_apple_google_pay', $this->method->getIdentifier());
    }

    public function testBuildIdentifier(): void
    {
        $this->assertEquals(
            'stripe_apple_google_pay',
            StripeAppleGooglePaymentMethod::buildIdentifier('stripe')
        );
    }
}
