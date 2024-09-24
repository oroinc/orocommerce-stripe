<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Provider;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Provider\PaymentTransactionDataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentTransactionDataProviderTest extends TestCase
{
    private PaymentTransactionRepository|MockObject $transactionRepository;
    private PaymentTransactionDataProvider $provider;

    #[\Override]
    protected function setUp(): void
    {
        $this->transactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $this->provider = new PaymentTransactionDataProvider($this->transactionRepository);
    }

    /**
     * @dataProvider getDataForTestGetAvailableAmountToRefund
     */
    public function testGetAvailableAmountToRefund(array $transactions, float $expected)
    {
        $sourceTransaction = $this->getTransaction(100.00, PaymentMethodInterface::CAPTURE);
        $this->transactionRepository->expects($this->once())
            ->method('findSuccessfulRelatedTransactionsByAction')
            ->willReturn($transactions);

        $result = $this->provider->getAvailableAmountToRefund($sourceTransaction);
        $this->assertEquals($expected, $result);
    }

    public function getDataForTestGetAvailableAmountToRefund(): array
    {
        return [
            'Empty refund transactions' => [
                'transactions' => [],
                'expected' => 100.00
            ],
            'Transactions amount less than capture transaction amount' => [
                'transactions' => [
                    $this->getTransaction(40.00),
                    $this->getTransaction(30.00)
                ],
                'expected' => 30.00
            ],
            'Capture amount refunded in full' => [
                'transactions' => [
                    $this->getTransaction(60.00),
                    $this->getTransaction(40.00)
                ],
                'expected' => 0.00
            ]
        ];
    }

    private function getTransaction(float $amount, string $action = PaymentMethodInterface::REFUND): PaymentTransaction
    {
        $transaction = new PaymentTransaction();
        $transaction->setAction($action)
            ->setAmount($amount);

        return $transaction;
    }
}
