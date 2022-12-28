<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\Provider;

use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\Factory\StripePaymentMethodFactory;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionRegistry;
use Oro\Bundle\StripeBundle\Method\Provider\StripePaymentMethodsProvider;
use Oro\Bundle\StripeBundle\Method\StripePaymentMethod;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodsProviderTest extends TestCase
{
    private const IDENTIFIER1 = 'test1';
    private const IDENTIFIER2 = 'test2';
    private const WRONG_IDENTIFIER = 'wrong';

    private StripePaymentConfigsProvider|MockObject $configProvider;
    private StripePaymentMethodFactory|MockObject $factory;
    private StripePaymentMethodsProvider $methodProvider;
    private string $paymentConfigClass;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(StripePaymentMethodFactory::class);
        $this->configProvider = $this->createMock(StripePaymentConfigsProvider::class);
        $this->paymentConfigClass = StripePaymentConfig::class;
        $this->methodProvider = new StripePaymentMethodsProvider($this->configProvider, $this->factory);
    }

    public function hasPaymentMethodDataProvider(): array
    {
        return [
            'existingIdentifier' => [
                'identifier' => self::IDENTIFIER1,
                'expectedResult' => true,
            ],
            'notExistingIdentifier' => [
                'identifier' => self::WRONG_IDENTIFIER,
                'expectedResult' => false,
            ],
        ];
    }

    public function testGetPaymentMethodForCorrectIdentifier(): void
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $method = $this->methodProvider->getPaymentMethod(self::IDENTIFIER1);
        $this->assertInstanceOf(StripePaymentMethod::class, $method);
    }

    /**
     * @dataProvider hasPaymentMethodDataProvider
     */
    public function testHasPaymentMethod(string $identifier, bool $expectedResult)
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->assertEquals($expectedResult, $this->methodProvider->hasPaymentMethod($identifier));
    }

    public function testGetPaymentMethodForWrongIdentifier()
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->assertNull($this->methodProvider->getPaymentMethod(self::WRONG_IDENTIFIER));
    }

    public function testGetPaymentMethods(): void
    {
        $config1 = $this->buildPaymentConfig(self::IDENTIFIER1);
        $config2 = $this->buildPaymentConfig(self::IDENTIFIER2);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config1, $config2]);

        $registry = $this->createMock(PaymentActionRegistry::class);
        $method1 = new StripePaymentMethod($config1, $registry);
        $method2 = new StripePaymentMethod($config2, $registry);

        $this->factory->expects($this->exactly(2))
            ->method('create')
            ->withConsecutive([$config1], [$config2])
            ->willReturnOnConsecutiveCalls($method1, $method2);

        $this->assertEquals(
            [self::IDENTIFIER1 => $method1, self::IDENTIFIER2 => $method2],
            $this->methodProvider->getPaymentMethods()
        );
    }

    protected function buildPaymentConfig(string $identifier)
    {
        $config = $this->createMock($this->paymentConfigClass);
        $config->expects($this->any())
            ->method('getPaymentMethodIdentifier')
            ->willReturn($identifier);

        return $config;
    }
}
