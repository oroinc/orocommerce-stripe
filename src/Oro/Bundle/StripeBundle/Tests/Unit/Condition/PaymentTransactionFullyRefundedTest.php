<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Condition;

use Oro\Bundle\PaymentBundle\Context\PaymentContextInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Condition\PaymentTransactionFullyRefunded;
use Oro\Bundle\StripeBundle\Provider\PaymentTransactionDataProvider;
use Oro\Component\ConfigExpression\Exception\InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentTransactionFullyRefundedTest extends TestCase
{
    private PaymentTransactionDataProvider&MockObject $transactionDataProvider;
    private PaymentTransactionFullyRefunded $condition;

    #[\Override]
    protected function setUp(): void
    {
        $this->transactionDataProvider = $this->createMock(PaymentTransactionDataProvider::class);
        $this->condition = new PaymentTransactionFullyRefunded($this->transactionDataProvider);
    }

    public function testGetName(): void
    {
        $this->assertEquals('payment_transaction_was_fully_refunded', $this->condition->getName());
    }

    public function testInitializeException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "transaction" option');
        $this->condition->initialize([]);
    }

    public function testInitialize(): void
    {
        $this->assertInstanceOf(
            PaymentTransactionFullyRefunded::class,
            $this->condition->initialize([
                'transaction' => new PaymentTransaction()
            ])
        );
    }

    /**
     * @dataProvider availableRefundAmountDataProvider
     */
    public function testEvaluate(float $availableRefundAmount, bool $expected): void
    {
        $transaction = new PaymentTransaction();
        $context = $this->createMock(PaymentContextInterface::class);

        $this->transactionDataProvider->expects($this->once())
            ->method('getAvailableAmountToRefund')
            ->with($transaction)
            ->willReturn($availableRefundAmount);

        $this->condition->initialize([
            'transaction' => $transaction,
            'context' => $context
        ]);

        $this->assertSame($expected, $this->condition->evaluate($context));
    }

    public function availableRefundAmountDataProvider(): \Generator
    {
        yield [
            10.00,
            false
        ];

        yield [
            0.00,
            true
        ];
    }
}
