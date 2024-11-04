<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client;

use LogicException;
use Oro\Bundle\StripeBundle\Client\StripeClientFactory;
use Oro\Bundle\StripeBundle\Client\StripeGateway;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use PHPUnit\Framework\TestCase;

class StripeClientFactoryTest extends TestCase
{
    private StripeClientFactory $factory;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = new StripeClientFactory();
    }

    public function testCreateGatewaySuccess(): void
    {
        $secretKey = 'key';
        $config = new StripePaymentConfig([StripePaymentConfig::SECRET_KEY => $secretKey]);
        $expected = new StripeGateway($secretKey);

        $this->assertEquals($expected, $this->factory->create($config));
    }

    public function testCreateGatewayFailed(): void
    {
        $this->expectException(LogicException::class);
        $this->factory->create(new StripePaymentConfig());
    }
}
