<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use LogicException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\PurchaseRequest;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use PHPUnit\Framework\TestCase;

class PurchaseRequestTest extends TestCase
{
    private PurchaseRequest $request;

    protected function setUp(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            'stripePaymentMethodId' => 1
        ])]);
        $config = new StripePaymentConfig([
            StripePaymentConfig::PAYMENT_ACTION => StripePaymentActionMapper::MANUAL
        ]);

        $this->request = new PurchaseRequest($config, $paymentTransaction);
    }

    public function testGetRequestDataWithPaymentIntentId(): void
    {
        $result = $this->request->getRequestData();
        $expected = [
            'payment_method' => 1,
            'amount' => 0.0,
            'currency' => null,
            'confirmation_method' => 'manual',
            'capture_method' => 'manual',
            'confirm' => 'true',
            'metadata' => [
                'order_id' => null
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetRequestDataWithoutPaymentIntentId(): void
    {
        $this->expectException(LogicException::class);
        $request = new PurchaseRequest(new StripePaymentConfig(), new PaymentTransaction());
        $request->getRequestData();
    }

    public function testGetPaymentId(): void
    {
        $this->assertNull($this->request->getPaymentId());
    }
}
