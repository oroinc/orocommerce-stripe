<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\ReAuthorization\Provider;

use Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider\ReAuthorizePaymentTransactionsProviderComposite;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider\ReAuthorizePaymentTransactionsProviderInterface;
use PHPUnit\Framework\TestCase;

final class ReAuthorizePaymentTransactionsProviderCompositeTest extends TestCase
{
    public function testGetPaymentTransactionIdsWithNoProviders(): void
    {
        $composite = new ReAuthorizePaymentTransactionsProviderComposite([]);

        $result = iterator_to_array($composite->getPaymentTransactionIds());

        self::assertSame([], $result);
    }

    public function testGetPaymentTransactionIdsWithSingleProvider(): void
    {
        $provider = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([1, 2, 3]));

        $composite = new ReAuthorizePaymentTransactionsProviderComposite([$provider]);

        $result = iterator_to_array($composite->getPaymentTransactionIds());

        self::assertSame([1, 2, 3], $result);
    }

    public function testGetPaymentTransactionIdsWithMultipleProviders(): void
    {
        $provider1 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider1
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([1, 2]));

        $provider2 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider2
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([3, 4]));

        $provider3 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider3
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([5]));

        $composite = new ReAuthorizePaymentTransactionsProviderComposite([$provider1, $provider2, $provider3]);

        $result = iterator_to_array($composite->getPaymentTransactionIds());

        self::assertSame([1, 2, 3, 4, 5], $result);
    }

    public function testGetPaymentTransactionIdsWithEmptyProviders(): void
    {
        $provider1 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider1
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([]));

        $provider2 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider2
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([]));

        $composite = new ReAuthorizePaymentTransactionsProviderComposite([$provider1, $provider2]);

        $result = iterator_to_array($composite->getPaymentTransactionIds());

        self::assertSame([], $result);
    }

    public function testGetPaymentTransactionIdsWithMixedProviders(): void
    {
        $provider1 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider1
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([]));

        $provider2 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider2
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([1, 2]));

        $provider3 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider3
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([]));

        $provider4 = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider4
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn(new \ArrayIterator([3]));

        $composite = new ReAuthorizePaymentTransactionsProviderComposite(
            [$provider1, $provider2, $provider3, $provider4]
        );

        $result = iterator_to_array($composite->getPaymentTransactionIds());

        self::assertSame([1, 2, 3], $result);
    }

    public function testGetPaymentTransactionIdsWithGeneratorProviders(): void
    {
        $provider = $this->createMock(ReAuthorizePaymentTransactionsProviderInterface::class);
        $provider
            ->expects(self::once())
            ->method('getPaymentTransactionIds')
            ->willReturn($this->createGenerator([1, 2, 3]));

        $composite = new ReAuthorizePaymentTransactionsProviderComposite([$provider]);

        $result = iterator_to_array($composite->getPaymentTransactionIds());

        self::assertSame([1, 2, 3], $result);
    }

    private function createGenerator(array $values): \Generator
    {
        foreach ($values as $value) {
            yield $value;
        }
    }
}
