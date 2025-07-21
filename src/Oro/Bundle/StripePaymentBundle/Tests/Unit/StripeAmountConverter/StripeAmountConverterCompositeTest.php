<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeAmountConverter;

use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterComposite;
use Oro\Bundle\StripePaymentBundle\StripeAmountConverter\StripeAmountConverterInterface;
use PHPUnit\Framework\TestCase;

final class StripeAmountConverterCompositeTest extends TestCase
{
    public function testIsApplicableWithNoConverters(): void
    {
        $composite = new StripeAmountConverterComposite([]);

        self::assertFalse($composite->isApplicable('USD'));
    }

    public function testIsApplicableWithSingleApplicableConverter(): void
    {
        $converter = $this->createMock(StripeAmountConverterInterface::class);
        $converter
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(true);

        $composite = new StripeAmountConverterComposite([$converter]);

        self::assertTrue($composite->isApplicable('USD'));
    }

    public function testIsApplicableWithSingleNonApplicableConverter(): void
    {
        $converter = $this->createMock(StripeAmountConverterInterface::class);
        $converter
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(false);

        $composite = new StripeAmountConverterComposite([$converter]);

        self::assertFalse($composite->isApplicable('USD'));
    }

    public function testIsApplicableWithMultipleConverters(): void
    {
        $converter1 = $this->createMock(StripeAmountConverterInterface::class);
        $converter1
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(false);

        $converter2 = $this->createMock(StripeAmountConverterInterface::class);
        $converter2
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(true);

        $converter3 = $this->createMock(StripeAmountConverterInterface::class);
        $converter3
            ->expects(self::never())
            ->method('isApplicable');

        $composite = new StripeAmountConverterComposite([$converter1, $converter2, $converter3]);

        self::assertTrue($composite->isApplicable('USD'));
    }

    public function testConvertToStripeFormatWithApplicableConverter(): void
    {
        $converter1 = $this->createMock(StripeAmountConverterInterface::class);
        $converter1
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(false);

        $converter2 = $this->createMock(StripeAmountConverterInterface::class);
        $converter2
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(true);
        $converter2
            ->expects(self::once())
            ->method('convertToStripeFormat')
            ->with(100.50, 'USD', 'en_US')
            ->willReturn(10050);

        $composite = new StripeAmountConverterComposite([$converter1, $converter2]);

        self::assertSame(10050, $composite->convertToStripeFormat(100.50, 'USD', 'en_US'));
    }

    public function testConvertToStripeFormatWithNoApplicableConverter(): void
    {
        $converter = $this->createMock(StripeAmountConverterInterface::class);
        $converter
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(false);

        $composite = new StripeAmountConverterComposite([$converter]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No converter found for currency "USD"');

        $composite->convertToStripeFormat(100.50, 'USD');
    }

    public function testConvertFromStripeFormatWithApplicableConverter(): void
    {
        $converter1 = $this->createMock(StripeAmountConverterInterface::class);
        $converter1
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(false);

        $converter2 = $this->createMock(StripeAmountConverterInterface::class);
        $converter2
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(true);
        $converter2
            ->expects(self::once())
            ->method('convertFromStripeFormat')
            ->with(10050, 'USD', 'en_US')
            ->willReturn(100.50);

        $composite = new StripeAmountConverterComposite([$converter1, $converter2]);

        self::assertSame(100.50, $composite->convertFromStripeFormat(10050, 'USD', 'en_US'));
    }

    public function testConvertFromStripeFormatWithNoApplicableConverter(): void
    {
        $converter = $this->createMock(StripeAmountConverterInterface::class);
        $converter
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(false);

        $composite = new StripeAmountConverterComposite([$converter]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No converter found for currency "USD"');

        $composite->convertFromStripeFormat(10050, 'USD');
    }

    public function testConvertToStripeFormatStopsOnFirstApplicableConverter(): void
    {
        $converter1 = $this->createMock(StripeAmountConverterInterface::class);
        $converter1
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(true);
        $converter1
            ->expects(self::once())
            ->method('convertToStripeFormat')
            ->with(100.50, 'USD')
            ->willReturn(10050);

        $converter2 = $this->createMock(StripeAmountConverterInterface::class);
        $converter2
            ->expects(self::never())
            ->method('isApplicable');

        $composite = new StripeAmountConverterComposite([$converter1, $converter2]);

        self::assertSame(10050, $composite->convertToStripeFormat(100.50, 'USD'));
    }

    public function testConvertFromStripeFormatStopsOnFirstApplicableConverter(): void
    {
        $converter1 = $this->createMock(StripeAmountConverterInterface::class);
        $converter1
            ->expects(self::once())
            ->method('isApplicable')
            ->with('USD')
            ->willReturn(true);
        $converter1
            ->expects(self::once())
            ->method('convertFromStripeFormat')
            ->with(10050, 'USD')
            ->willReturn(100.50);

        $converter2 = $this->createMock(StripeAmountConverterInterface::class);
        $converter2
            ->expects(self::never())
            ->method('isApplicable');

        $composite = new StripeAmountConverterComposite([$converter1, $converter2]);

        self::assertSame(100.50, $composite->convertFromStripeFormat(10050, 'USD'));
    }
}
