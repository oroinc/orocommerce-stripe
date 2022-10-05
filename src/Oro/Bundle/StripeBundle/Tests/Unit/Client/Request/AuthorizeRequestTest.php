<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client\Request;

use LogicException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\AuthorizeRequest;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use PHPUnit\Framework\TestCase;

class AuthorizeRequestTest extends TestCase
{
    private AuthorizeRequest $request;
    private PaymentTransaction $paymentTransaction;

    protected function setUp(): void
    {
        $this->paymentTransaction = new PaymentTransaction();
        $config = new StripePaymentConfig([
            StripePaymentConfig::PAYMENT_ACTION => StripePaymentActionMapper::AUTOMATIC
        ]);

        $this->request = new AuthorizeRequest($config, $this->paymentTransaction);
    }

    /**
     * @dataProvider requestDataProvider
     */
    public function testGetRequestDataWithPaymentIntentId(array $additionalOptions, array $expected): void
    {
        $this->paymentTransaction->setTransactionOptions(['additionalData' => json_encode($additionalOptions)]);
        $result = $this->request->getRequestData();
        $this->assertEquals($expected, $result);
    }

    public function requestDataProvider(): \Generator
    {
        yield [
            [
                'stripePaymentMethodId' => 1,
                SetupIntentResponse::SETUP_INTENT_ID_PARAM => 'si_001',
                'customerId' => 'cus_001'
            ],
            [
                'payment_method' => 1,
                'amount' => 0.0,
                'currency' => null,
                'confirmation_method' => 'manual',
                'capture_method' => 'manual',
                'confirm' => true,
                'metadata' => [
                    'order_id' => null
                ],
                'off_session' => true,
                'customer' => 'cus_001'
            ]
        ];

        yield [
            [
                'stripePaymentMethodId' => 1
            ],
            [
                'payment_method' => 1,
                'amount' => 0.0,
                'currency' => null,
                'confirmation_method' => 'manual',
                'capture_method' => 'manual',
                'confirm' => true,
                'metadata' => [
                    'order_id' => null
                ],
                'off_session' => false
            ]
        ];
    }

    public function testGetRequestDataWithoutPaymentIntentId(): void
    {
        $this->expectException(LogicException::class);
        $request = new AuthorizeRequest(new StripePaymentConfig(), new PaymentTransaction());
        $request->getRequestData();
    }

    public function testGetPaymentId(): void
    {
        $this->assertNull($this->request->getPaymentId());
    }
}
