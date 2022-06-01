<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use InvalidArgumentException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\ConfirmRequest;
use PHPUnit\Framework\TestCase;

class ConfirmRequestTest extends TestCase
{
    private ConfirmRequest $request;

    protected function setUp(): void
    {
        $this->request = new ConfirmRequest(new PaymentTransaction());
    }

    public function testGetRequestData(): void
    {
        $this->assertEquals([], $this->request->getRequestData());
    }

    public function testGetPaymentIdWithoutPaymentIntentId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->request->getPaymentId();
    }

    public function testGetPaymentIdWithPaymentIntentId(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
        ])]);

        $request = new ConfirmRequest($paymentTransaction);
        $this->assertEquals(1, $request->getPaymentId());
    }
}
