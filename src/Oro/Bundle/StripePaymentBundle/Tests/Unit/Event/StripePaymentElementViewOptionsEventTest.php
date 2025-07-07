<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Event;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\StripePaymentBundle\Event\StripePaymentElementViewOptionsEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use PHPUnit\Framework\TestCase;

final class StripePaymentElementViewOptionsEventTest extends TestCase
{
    private PaymentContextInterface $paymentContext;

    private StripePaymentElementConfig $stripePaymentElementConfig;

    private array $initialViewOptions;

    protected function setUp(): void
    {
        $this->paymentContext = $this->createMock(PaymentContextInterface::class);
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);
        $this->initialViewOptions = ['option1' => 'value1', 'option2' => 'value2'];
    }

    public function testConstructorAndGetters(): void
    {
        $event = new StripePaymentElementViewOptionsEvent(
            $this->paymentContext,
            $this->stripePaymentElementConfig,
            $this->initialViewOptions
        );

        self::assertSame($this->paymentContext, $event->getPaymentContext());
        self::assertSame($this->stripePaymentElementConfig, $event->getStripePaymentElementConfig());
        self::assertSame($this->initialViewOptions, $event->getViewOptions());
    }

    /**
     * @dataProvider viewOptionsDataProvider
     */
    public function testSetViewOptions(array $newOptions): void
    {
        $event = new StripePaymentElementViewOptionsEvent(
            $this->paymentContext,
            $this->stripePaymentElementConfig,
            $this->initialViewOptions
        );

        $event->setViewOptions($newOptions);

        self::assertSame($newOptions, $event->getViewOptions());
    }

    public function viewOptionsDataProvider(): array
    {
        return [
            'empty array' => [
                'newOptions' => [],
            ],
            'different options' => [
                'newOptions' => ['new' => 'value'],
            ],
            'multiple options' => [
                'newOptions' => ['a' => 1, 'b' => 2, 'c' => 3],
            ],
        ];
    }

    /**
     * @dataProvider addViewOptionDataProvider
     */
    public function testAddViewOption(string $name, mixed $value, array $expectedOptions): void
    {
        $event = new StripePaymentElementViewOptionsEvent(
            $this->paymentContext,
            $this->stripePaymentElementConfig,
            $this->initialViewOptions
        );

        $event->addViewOption($name, $value);

        self::assertSame($expectedOptions, $event->getViewOptions());
    }

    public function addViewOptionDataProvider(): array
    {
        return [
            'new option' => [
                'name' => 'option3',
                'value' => 'value3',
                'expectedOptions' => ['option1' => 'value1', 'option2' => 'value2', 'option3' => 'value3'],
            ],
            'overwrite existing' => [
                'name' => 'option1',
                'value' => 'new_value',
                'expectedOptions' => ['option1' => 'new_value', 'option2' => 'value2'],
            ],
            'null value' => [
                'name' => 'option3',
                'value' => null,
                'expectedOptions' => ['option1' => 'value1', 'option2' => 'value2', 'option3' => null],
            ],
            'array value' => [
                'name' => 'nested',
                'value' => ['a' => 1],
                'expectedOptions' => ['option1' => 'value1', 'option2' => 'value2', 'nested' => ['a' => 1]],
            ],
        ];
    }

    /**
     * @dataProvider getViewOptionDataProvider
     */
    public function testGetViewOption(string $name, mixed $expectedValue): void
    {
        $event = new StripePaymentElementViewOptionsEvent(
            $this->paymentContext,
            $this->stripePaymentElementConfig,
            $this->initialViewOptions
        );

        self::assertSame($expectedValue, $event->getViewOption($name));
    }

    public function getViewOptionDataProvider(): array
    {
        return [
            'existing option' => [
                'name' => 'option1',
                'expectedValue' => 'value1',
            ],
            'non-existing option' => [
                'name' => 'option3',
                'expectedValue' => null,
            ],
            'existing option with null value' => [
                'name' => 'option2',
                'expectedValue' => 'value2',
            ],
        ];
    }
}
