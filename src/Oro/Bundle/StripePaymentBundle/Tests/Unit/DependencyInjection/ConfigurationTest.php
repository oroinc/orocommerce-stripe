<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\DependencyInjection;

use Oro\Bundle\StripePaymentBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
final class ConfigurationTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
    }

    public function testConfigurationImplementsInterface(): void
    {
        self::assertInstanceOf(ConfigurationInterface::class, $this->configuration);
    }

    public function testRootNodeName(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();

        self::assertEquals(
            Configuration::ROOT_NODE,
            $treeBuilder->getRootNode()->getNode(true)->getName()
        );
    }

    public function testDefaultConfiguration(): void
    {
        $processor = new Processor();
        $config = $processor->processConfiguration($this->configuration, []);

        self::assertEquals(
            [
                'payment_method_types' => [],
            ],
            $config
        );
    }

    public function testPaymentMethodTypesConfiguration(): void
    {
        $config = [
            'payment_method_types' => [
                'card' => ['manual_capture' => true],
                'ideal' => ['manual_capture' => false],
            ],
        ];

        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($this->configuration, [$config]);

        self::assertEquals($config, $processedConfig);
    }

    public function testPaymentMethodTypesMissingManualCapture(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'The child config "manual_capture" under "oro_stripe_payment.payment_method_types.card" must be configured'
        );

        $config = [
            'payment_method_types' => [
                'card' => [],
            ],
        ];

        $processor = new Processor();
        $processor->processConfiguration($this->configuration, [$config]);
    }

    public function testChargeAmountMinimumConfiguration(): void
    {
        $config = [
            'charge_amount' => [
                'minimum' => [
                    'USD' => 0.5,
                    'EUR' => 0.5,
                    '*' => 1.0,
                ],
            ],
        ];

        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($this->configuration, [$config]);

        self::assertEquals(
            [
                'charge_amount' => [
                    'minimum' => [
                        'USD' => 0.5,
                        'EUR' => 0.5,
                        '*' => 1.0,
                    ],
                    'maximum' => [],
                    'decimal_places' => [],
                ],
                'payment_method_types' => [],
            ],
            $processedConfig
        );
    }

    public function testChargeAmountMaximumConfiguration(): void
    {
        $config = [
            'charge_amount' => [
                'maximum' => [
                    'USD' => 999999.99,
                    'EUR' => 500000.0,
                    '*' => 1000000.0,
                ],
            ],
        ];

        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($this->configuration, [$config]);

        self::assertEquals(
            [
                'charge_amount' => [
                    'maximum' => [
                        'USD' => 999999.99,
                        'EUR' => 500000.0,
                        '*' => 1000000.0,
                    ],
                    'minimum' => [],
                    'decimal_places' => [],
                ],
                'payment_method_types' => [],
            ],
            $processedConfig
        );
    }

    public function testChargeAmountDecimalPlacesSimpleConfiguration(): void
    {
        $config = [
            'charge_amount' => [
                'decimal_places' => [
                    'BIF' => 0,
                    'HUF' => 2,
                ],
            ],
        ];

        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($this->configuration, [$config]);

        self::assertEquals(
            [
                'charge_amount' => [
                    'minimum' => [],
                    'maximum' => [],
                    'decimal_places' => [
                        'BIF' => ['decimal_places' => 0, 'fractionless' => false],
                        'HUF' => ['decimal_places' => 2, 'fractionless' => false],
                    ],
                ],
                'payment_method_types' => [],
            ],
            $processedConfig
        );
    }

    public function testChargeAmountDecimalPlacesFullConfiguration(): void
    {
        $config = [
            'charge_amount' => [
                'decimal_places' => [
                    'ISK' => ['decimal_places' => 2, 'fractionless' => true],
                    'CLP' => ['decimal_places' => 0, 'fractionless' => false],
                ],
            ],
        ];

        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($this->configuration, [$config]);

        self::assertEquals(
            [
                'charge_amount' => [
                    'decimal_places' => [
                        'ISK' => ['decimal_places' => 2, 'fractionless' => true],
                        'CLP' => ['decimal_places' => 0, 'fractionless' => false],
                    ],
                    'minimum' => [],
                    'maximum' => [],
                ],
                'payment_method_types' => [],
            ],
            $processedConfig
        );
    }

    public function testChargeAmountDecimalPlacesInvalidDecimalPlaces(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'The value -1 is too small for path "oro_stripe_payment.charge_amount.decimal_places.USD.decimal_places". '
            . 'Should be greater than or equal to 0'
        );

        $config = [
            'charge_amount' => [
                'decimal_places' => [
                    'USD' => ['decimal_places' => -1],
                ],
            ],
        ];

        $processor = new Processor();
        $processor->processConfiguration($this->configuration, [$config]);
    }

    public function testChargeAmountDecimalPlacesTooManyDecimalPlaces(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(
            'The value 5 is too big for path "oro_stripe_payment.charge_amount.decimal_places.USD.decimal_places". '
            . 'Should be less than or equal to 4'
        );

        $config = [
            'charge_amount' => [
                'decimal_places' => [
                    'USD' => ['decimal_places' => 5],
                ],
            ],
        ];

        $processor = new Processor();
        $processor->processConfiguration($this->configuration, [$config]);
    }

    public function testFullConfiguration(): void
    {
        $config = [
            'payment_method_types' => [
                'card' => ['manual_capture' => true],
                'ideal' => ['manual_capture' => false],
            ],
            'charge_amount' => [
                'minimum' => ['USD' => 0.5, 'EUR' => 0.5],
                'maximum' => ['USD' => 999999.99, 'EUR' => 500000.0],
                'decimal_places' => [
                    'BIF' => 0,
                    'HUF' => 2,
                    'ISK' => ['decimal_places' => 2, 'fractionless' => true],
                ],
            ],
        ];

        $processor = new Processor();
        $processedConfig = $processor->processConfiguration($this->configuration, [$config]);

        self::assertEquals([
            'payment_method_types' => [
                'card' => ['manual_capture' => true],
                'ideal' => ['manual_capture' => false],
            ],
            'charge_amount' => [
                'minimum' => ['USD' => 0.5, 'EUR' => 0.5],
                'maximum' => ['USD' => 999999.99, 'EUR' => 500000.0],
                'decimal_places' => [
                    'BIF' => ['decimal_places' => 0, 'fractionless' => false],
                    'HUF' => ['decimal_places' => 2, 'fractionless' => false],
                    'ISK' => ['decimal_places' => 2, 'fractionless' => true],
                ],
            ],
        ], $processedConfig);
    }
}
