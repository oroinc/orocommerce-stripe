<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeClient;

use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientConfigInterface;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripeClientFactoryTest extends TestCase
{
    private StripeClientFactory $factory;

    private array $defaultConfig = ['api_key' => 'default_key'];

    protected function setUp(): void
    {
        $this->factory = new StripeClientFactory($this->defaultConfig);
    }

    public function testCreateStripeClientWithDefaultConfig(): void
    {
        $stripeConfig = $this->createStripeConfig($this->defaultConfig);
        $stripeClient = $this->factory->createStripeClient($stripeConfig);

        self::assertEquals(new LoggingStripeClient($this->defaultConfig), $stripeClient);
    }

    public function testCreateStripeClientWithCustomConfig(): void
    {
        $customConfig = ['api_key' => 'custom_key'];
        $stripeConfig = $this->createStripeConfig($customConfig);
        $stripeClient = $this->factory->createStripeClient($stripeConfig);

        self::assertEquals(new LoggingStripeClient($customConfig), $stripeClient);
    }

    public function testCreateStripeClientReturnsSameInstanceForSameConfig(): void
    {
        $config1 = $this->createStripeConfig(['api_key' => 'same_key']);
        $config2 = $this->createStripeConfig(['api_key' => 'same_key']);

        $client1 = $this->factory->createStripeClient($config1);
        $client2 = $this->factory->createStripeClient($config2);

        self::assertSame($client1, $client2);
    }

    public function testCreateStripeClientReturnsDifferentInstanceForDifferentConfig(): void
    {
        $config1 = $this->createStripeConfig(['api_key' => 'key1']);
        $config2 = $this->createStripeConfig(['api_key' => 'key2']);

        $client1 = $this->factory->createStripeClient($config1);
        $client2 = $this->factory->createStripeClient($config2);

        self::assertNotSame($client1, $client2);
    }

    public function testCreateStripeClientMergesConfigWithDefaults(): void
    {
        $customConfig = ['api_key' => 'custom_key'];
        $expectedConfig = $customConfig + $this->defaultConfig;

        $stripeConfig = $this->createStripeConfig($customConfig);
        $stripeClient = $this->factory->createStripeClient($stripeConfig);

        self::assertEquals(new LoggingStripeClient($expectedConfig), $stripeClient);
    }

    public function testResetClearsAllClients(): void
    {
        $stripeConfig = $this->createStripeConfig(['api_key' => 'test_key']);
        $stripeClient1 = $this->factory->createStripeClient($stripeConfig);

        $this->factory->reset();

        $stripeClient2 = $this->factory->createStripeClient($stripeConfig);
        self::assertNotSame($stripeClient1, $stripeClient2);
    }

    public function testCreateStripeClientWithComplexConfig(): void
    {
        $complexConfig = [
            'api_key' => 'complex_key',
            'api_base' => 'https://api.example.com',
        ];
        $expectedConfig = $complexConfig + $this->defaultConfig;

        $stripeConfig = $this->createStripeConfig($complexConfig);
        $stripeClient = $this->factory->createStripeClient($stripeConfig);

        self::assertEquals(new LoggingStripeClient($expectedConfig), $stripeClient);
    }

    private function createStripeConfig(array $config): MockObject&StripeClientConfigInterface
    {
        $stripeClientConfigAware = $this->createMock(StripeClientConfigInterface::class);
        $stripeClientConfigAware
            ->method('getStripeClientConfig')
            ->willReturn($config);

        return $stripeClientConfigAware;
    }
}
