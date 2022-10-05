<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\CreateSetupIntentRequest;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\TestCase;

class CreateSetupIntentRequestTest extends TestCase
{
    use EntityTrait;

    private PaymentTransaction $paymentTransaction;
    private CreateSetupIntentRequest $request;

    protected function setUp(): void
    {
        $this->paymentTransaction = new PaymentTransaction();

        $this->request = new CreateSetupIntentRequest(
            $this->paymentTransaction
        );
    }

    public function testGetRequestData()
    {
        $this->paymentTransaction->setEntityClass(Order::class);
        $this->paymentTransaction->setEntityIdentifier(100);
        $this->paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 'pm1',
                CustomerResponse::CUSTOMER_ID_PARAM => 'cus_001'
            ])
        ]);

        $expected = [
            'payment_method' => 'pm1',
            'confirm' => true,
            'customer' => 'cus_001',
            'usage' => 'off_session',
            'metadata' => [
                'order_id' => 100
            ]
        ];

        $this->assertEquals($expected, $this->request->getRequestData());
    }

    public function testGetPaymentId(): void
    {
        $this->assertNull($this->request->getPaymentId());
    }
}
