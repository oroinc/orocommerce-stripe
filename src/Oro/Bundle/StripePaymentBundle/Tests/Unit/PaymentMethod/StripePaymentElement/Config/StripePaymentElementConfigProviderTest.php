<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\PaymentMethod\StripePaymentElement\Config;

use Oro\Bundle\StripePaymentBundle\Entity\Repository\StripePaymentElementSettingsRepository;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigFactory;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Config\StripeReAuthorizationConfigProviderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class StripePaymentElementConfigProviderTest extends TestCase
{
    private StripePaymentElementConfigProvider $provider;

    private MockObject&StripePaymentElementSettingsRepository $stripePaymentElementSettingsRepository;

    private MockObject&StripePaymentElementConfigFactory $stripePaymentElementConfigFactory;

    protected function setUp(): void
    {
        $this->stripePaymentElementSettingsRepository = $this->createMock(
            StripePaymentElementSettingsRepository::class
        );
        $this->stripePaymentElementConfigFactory = $this->createMock(StripePaymentElementConfigFactory::class);

        $this->provider = new StripePaymentElementConfigProvider(
            $this->stripePaymentElementSettingsRepository,
            $this->stripePaymentElementConfigFactory
        );
    }

    public function testImplementsRequiredInterfaces(): void
    {
        self::assertInstanceOf(StripeReAuthorizationConfigProviderInterface::class, $this->provider);
        self::assertInstanceOf(ResetInterface::class, $this->provider);
    }

    public function testGetPaymentConfigsWhenEmpty(): void
    {
        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([]);

        $result = $this->provider->getPaymentConfigs();

        self::assertIsArray($result);
        self::assertEmpty($result);

        // Test that subsequent calls use cached result.
        $result2 = $this->provider->getPaymentConfigs();
        self::assertSame($result, $result2);
    }

    public function testGetPaymentConfigsWithMultipleSettings(): void
    {
        $stripePaymentElementSettings1 = $this->createMock(StripePaymentElementSettings::class);
        $stripePaymentElementSettings2 = $this->createMock(StripePaymentElementSettings::class);

        $stripePaymentElementConfig1 = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig1
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element_11');

        $stripePaymentElementConfig2 = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig2
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element_22');

        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([$stripePaymentElementSettings1, $stripePaymentElementSettings2]);

        $this->stripePaymentElementConfigFactory
            ->expects(self::exactly(2))
            ->method('createConfig')
            ->withConsecutive(
                [$stripePaymentElementSettings1],
                [$stripePaymentElementSettings2]
            )
            ->willReturnOnConsecutiveCalls($stripePaymentElementConfig1, $stripePaymentElementConfig2);

        $result = $this->provider->getPaymentConfigs();

        self::assertCount(2, $result);
        self::assertArrayHasKey('stripe_payment_element_11', $result);
        self::assertArrayHasKey('stripe_payment_element_22', $result);
        self::assertSame($stripePaymentElementConfig1, $result['stripe_payment_element_11']);
        self::assertSame($stripePaymentElementConfig2, $result['stripe_payment_element_22']);
    }

    public function testGetPaymentConfigWhenExists(): void
    {
        $stripePaymentElementSettings = $this->createMock(StripePaymentElementSettings::class);

        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([$stripePaymentElementSettings]);

        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripePaymentElementConfig);

        $result = $this->provider->getPaymentConfig('stripe_payment_element_11');

        self::assertSame($stripePaymentElementConfig, $result);
    }

    public function testGetReAuthorizationConfigWhenExists(): void
    {
        $stripePaymentElementSettings = $this->createMock(StripePaymentElementSettings::class);

        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([$stripePaymentElementSettings]);

        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripePaymentElementConfig);

        $result = $this->provider->getReAuthorizationConfig('stripe_payment_element_11');

        self::assertSame($stripePaymentElementConfig, $result);
    }

    public function testGetWebhookEndpointConfigWhenExists(): void
    {
        $stripePaymentElementSettings = $this->createMock(StripePaymentElementSettings::class);
        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);

        $webhookAccessId = 'sample_id';
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('getWebhookAccessId')
            ->willReturn($webhookAccessId);

        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([$stripePaymentElementSettings]);

        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripePaymentElementConfig);

        $result = $this->provider->getStripeWebhookEndpointConfig($webhookAccessId);

        self::assertSame($stripePaymentElementConfig, $result);
    }

    public function testGetPaymentConfigWhenNotExists(): void
    {
        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([]);

        $result = $this->provider->getPaymentConfig('nonexistent');

        self::assertNull($result);
    }

    public function testGetReAuthorizationConfigWhenNotExists(): void
    {
        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([]);

        $result = $this->provider->getReAuthorizationConfig('nonexistent');

        self::assertNull($result);
    }

    public function testGetWebhookEndpointConfigWhenNotExists(): void
    {
        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([]);

        $result = $this->provider->getStripeWebhookEndpointConfig('nonexistent');

        self::assertNull($result);
    }

    public function testResetClearsCache(): void
    {
        $stripePaymentElementSettings = $this->createMock(StripePaymentElementSettings::class);

        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig
            ->expects(self::exactly(2))
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementSettingsRepository
            ->expects(self::exactly(2))
            ->method('findEnabledSettings')
            ->willReturn([$stripePaymentElementSettings]);

        $this->stripePaymentElementConfigFactory
            ->expects(self::exactly(2))
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripePaymentElementConfig);

        // First call - populate cache
        $this->provider->getPaymentConfigs();

        // Reset cache
        $this->provider->reset();

        // Second call - should fetch fresh data
        $this->provider->getPaymentConfigs();
    }

    public function testCacheIsSharedBetweenMethods(): void
    {
        $stripePaymentElementSettings = $this->createMock(StripePaymentElementSettings::class);

        $stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $stripePaymentElementConfig
            ->expects(self::once())
            ->method('getPaymentMethodIdentifier')
            ->willReturn('stripe_payment_element_11');

        $this->stripePaymentElementSettingsRepository
            ->expects(self::once())
            ->method('findEnabledSettings')
            ->willReturn([$stripePaymentElementSettings]);

        $this->stripePaymentElementConfigFactory
            ->expects(self::once())
            ->method('createConfig')
            ->with($stripePaymentElementSettings)
            ->willReturn($stripePaymentElementConfig);

        // Call one method to populate cache
        $this->provider->getPaymentConfigs();

        // Call other methods - should use cached data
        $result1 = $this->provider->getPaymentConfig('stripe_payment_element_11');
        $result2 = $this->provider->getReAuthorizationConfig('stripe_payment_element_11');

        self::assertSame($stripePaymentElementConfig, $result1);
        self::assertSame($stripePaymentElementConfig, $result2);
    }
}
