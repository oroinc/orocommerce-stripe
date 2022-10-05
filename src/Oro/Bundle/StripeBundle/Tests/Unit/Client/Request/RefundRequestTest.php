<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\RefundRequest;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class RefundRequestTest extends TestCase
{
    /**
     * @dataProvider unsupportedPaymentTransactionsDataProvider
     */
    public function testGetPaymentIdExceptions(PaymentTransaction $paymentTransaction): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $request = new RefundRequest($paymentTransaction);
        $request->getPaymentId();
    }

    public function unsupportedPaymentTransactionsDataProvider(): \Generator
    {
        yield [new PaymentTransaction()];

        $sourceTransaction = new PaymentTransaction();
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction($sourceTransaction);
        yield [$transaction];

        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setResponse(['test' => true]);
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction($sourceTransaction);
        yield [$transaction];
    }

    public function testGetPaymentId(): void
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setResponse([PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 'test']);
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setSourcePaymentTransaction($sourceTransaction);

        $request = new RefundRequest($paymentTransaction);
        $this->assertEquals('test', $request->getPaymentId());
    }

    /**
     * @dataProvider paymentTransactionDataProvider
     */
    public function testGetRequestData(PaymentTransaction $paymentTransaction, array $expected)
    {
        $request = new RefundRequest($paymentTransaction);
        $this->assertEquals($expected, $request->getRequestData());
    }

    public function paymentTransactionDataProvider(): \Generator
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setResponse([PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 'test']);
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setSourcePaymentTransaction($sourceTransaction);
        yield [
            $paymentTransaction,
            [
                'payment_intent' => 'test',
                'reason' => 'requested_by_customer'
            ]
        ];

        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setResponse([PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 'test']);
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['refundReason' => 'abandoned']);
        $paymentTransaction->setSourcePaymentTransaction($sourceTransaction);
        yield [
            $paymentTransaction,
            [
                'payment_intent' => 'test',
                'reason' => 'abandoned'
            ]
        ];
    }
}
