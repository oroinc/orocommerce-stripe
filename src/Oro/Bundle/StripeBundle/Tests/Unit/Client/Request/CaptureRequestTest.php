<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use InvalidArgumentException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\CaptureRequest;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\TestCase;

class CaptureRequestTest extends TestCase
{
    /**
     * @dataProvider getTestRequestData
     * @param mixed $amount
     * @param mixed $expected
     */
    public function testGetRequestData($amount, $expected): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAmount($amount);
        $paymentTransaction->setCurrency('USD');

        $request = new CaptureRequest($paymentTransaction);
        $this->assertEquals(['amount_to_capture' => $expected], $request->getRequestData());
    }

    public function testPartialCapture()
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAmount(100);
        $paymentTransaction->setCurrency('USD');

        $request = new CaptureRequest($paymentTransaction, 50);
        $this->assertEquals(['amount_to_capture' => 5000], $request->getRequestData());
    }

    public function testGetPaymentIdWithoutPaymentIntentId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $request = new CaptureRequest(new PaymentTransaction());
        $request->getPaymentId();
    }

    public function testGetPaymentIdWithPaymentIntentId(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);

        $request = new CaptureRequest($paymentTransaction);
        $this->assertEquals(1, $request->getPaymentId());
    }

    public function getTestRequestData(): array
    {
        return [
            [
                'amount' => 0.0,
                'expected' => 0,
            ],
            [
                'amount' => 7.66,
                'expected' => 766,
            ],
            [
                'amount' => null,
                'expected' => 0,
            ],
            [
                'amount' => '',
                'expected' => 0,
            ],
            [
                'amount' => '3.44',
                'expected' => 344,
            ]
        ];
    }
}
