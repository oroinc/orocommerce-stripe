<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\View\Provider;

use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\View\Provider\StripePaymentMethodsViewProvider;
use Oro\Bundle\StripeBundle\Method\View\StripePaymentView;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StripePaymentMethodsViewProviderTest extends TestCase
{
    private const IDENTIFIER1 = 'test1';
    private const IDENTIFIER2 = 'test2';
    private const WRONG_IDENTIFIER = 'wrong';

    protected StripePaymentConfigsProvider|MockObject $configProvider;
    protected StripePaymentMethodsViewProvider $provider;
    protected string $paymentConfigClass;

    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(StripePaymentConfigsProvider::class);
        $this->paymentConfigClass = StripePaymentConfig::class;
        $this->provider = new StripePaymentMethodsViewProvider($this->configProvider);
    }

    public function testHasPaymentMethodViewForCorrectIdentifier(): void
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->assertTrue($this->provider->hasPaymentMethodView(self::IDENTIFIER1));
    }

    public function testHasPaymentMethodViewForWrongIdentifier(): void
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->assertFalse($this->provider->hasPaymentMethodView(self::WRONG_IDENTIFIER));
    }

    public function testGetPaymentMethodViewReturnsCorrectObject(): void
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $view = new StripePaymentView($this->createMock(StripePaymentConfig::class));
        $this->assertEquals($view, $this->provider->getPaymentMethodView(self::IDENTIFIER1));
    }

    public function testGetPaymentMethodViewForWrongIdentifier(): void
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->assertNull($this->provider->getPaymentMethodView(self::WRONG_IDENTIFIER));
    }

    public function testGetPaymentMethodViewsReturnsCorrectObjects(): void
    {
        $config1 = $this->buildPaymentConfig(self::IDENTIFIER1);
        $config2 = $this->buildPaymentConfig(self::IDENTIFIER2);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config1, $config2]);

        $view1 = new StripePaymentView($config1);
        $view2 = new StripePaymentView($config2);

        $this->assertEquals(
            [$view1, $view2],
            $this->provider->getPaymentMethodViews([self::IDENTIFIER1, self::IDENTIFIER2])
        );
    }

    public function testGetPaymentMethodViewsForWrongIdentifier(): void
    {
        $config = $this->buildPaymentConfig(self::IDENTIFIER1);

        $this->configProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn([$config]);

        $this->assertEmpty($this->provider->getPaymentMethodViews([self::WRONG_IDENTIFIER]));
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
