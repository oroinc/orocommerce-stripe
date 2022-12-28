<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Factory;

use Monolog\Logger;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\Factory\StripePaymentMethodFactory;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use Oro\Bundle\StripeBundle\Method\StripePaymentMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodFactoryTest extends TestCase
{
    private PaymentActionRegistry|MockObject $registry;
    private Logger|MockObject $logger;
    private StripePaymentMethodFactory $factory;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(PaymentActionRegistry::class);
        $this->logger = $this->createMock(Logger::class);

        $this->factory = new StripePaymentMethodFactory($this->registry, $this->logger);
    }

    public function testCreate(): void
    {
        $config = new StripePaymentConfig();
        $method = new StripePaymentMethod($config, $this->registry);
        $method->setLogger($this->logger);

        $this->assertEquals($method, $this->factory->create($config));
    }
}
