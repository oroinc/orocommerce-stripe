<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Request\CaptureRequest;
use Oro\Bundle\StripeBundle\Client\Request\ConfirmRequest;
use Oro\Bundle\StripeBundle\Client\Request\PurchaseRequest;
use Oro\Bundle\StripeBundle\Client\StripeGateway;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Tests\Unit\Utils\SetReflectionPropertyTrait;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\Service\PaymentIntentService;
use Stripe\StripeClient;

class StripeGatewayTest extends TestCase
{
    use SetReflectionPropertyTrait;

    /** @var \PHPUnit\Framework\MockObject\MockObject|PaymentIntentService  */
    private PaymentIntentService $paymentService;

    /** @var \PHPUnit\Framework\MockObject\MockObject|StripeClient  */
    private StripeClient $client;
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        $this->paymentService = $this->createMock(PaymentIntentService::class);
        $this->client = $this->createMock(StripeClient::class);
        $this->client->paymentIntents = $this->paymentService;

        $this->gateway = new StripeGateway('test');
        $this->setProperty(StripeGateway::class, $this->gateway, 'client', $this->client);
    }

    public function testPurchaseSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            'stripePaymentMethodId' => 1
        ])]);
        $config = new StripePaymentConfig([StripePaymentConfig::PAYMENT_ACTION => 'test']);

        $request = new PurchaseRequest($config, $paymentTransaction);
        $paymentIntent = new PaymentIntent();

        $this->paymentService->expects($this->once())
            ->method('create')
            ->with($request->getRequestData())
            ->willReturn($paymentIntent);

        $expected = new PaymentIntentResponse([]);
        $this->assertEquals($expected, $this->gateway->purchase($request));
    }

    public function testPurchaseFailed(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            'stripePaymentMethodId' => 1
        ])]);
        $config = new StripePaymentConfig([StripePaymentConfig::PAYMENT_ACTION => 'test']);

        $request = new PurchaseRequest($config, $paymentTransaction);
        $exception = new CardException('transaction declined');

        $this->paymentService->expects($this->once())
            ->method('create')
            ->with($request->getRequestData())
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('transaction declined');

        $this->gateway->purchase($request);
    }

    public function testConfirmSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
        ])]);

        $request = new ConfirmRequest($paymentTransaction);
        $paymentIntent = $this->createMock(PaymentIntent::class);
        $paymentIntent->expects($this->once())
            ->method('confirm')
            ->willReturn($paymentIntent);

        $paymentIntent->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'status' => 'succeeded'
            ]);

        $this->paymentService->expects($this->once())
            ->method('retrieve')
            ->with(1, [])
            ->willReturn($paymentIntent);

        $expected = new PaymentIntentResponse([
            'status' => 'succeeded'
        ]);
        $this->assertEquals($expected, $this->gateway->confirm($request));
    }

    public function testConfirmNorFoundPaymentIntent(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
        ])]);

        $request = new ConfirmRequest($paymentTransaction);

        $this->paymentService->expects($this->once())
            ->method('retrieve')
            ->with(1, [])
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Payment intent is not found');

        $this->gateway->confirm($request);
    }

    public function testConfirmApiErrorException(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions(['additionalData' => json_encode([
            ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
        ])]);

        $request = new ConfirmRequest($paymentTransaction);
        $paymentIntent = $this->createMock(PaymentIntent::class);

        $paymentIntent->expects($this->once())
            ->method('confirm')
            ->willThrowException(new CardException('transaction declined'));

        $this->paymentService->expects($this->once())
            ->method('retrieve')
            ->with(1, [])
            ->willReturn($paymentIntent);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('transaction declined');

        $this->gateway->confirm($request);
    }

    public function testCaptureSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);

        $request = new CaptureRequest($paymentTransaction);
        $paymentIntent = new PaymentIntent();

        $this->paymentService->expects($this->once())
            ->method('capture')
            ->with(1, ['amount_to_capture' => 0])
            ->willReturn($paymentIntent);

        $expected = new PaymentIntentResponse($paymentIntent->toArray());
        $this->assertEquals($expected, $this->gateway->capture($request));
    }

    public function testCaptureFailed(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);

        $request = new CaptureRequest($paymentTransaction);
        $exception = new CardException('insufficient funds');

        $this->paymentService->expects($this->once())
            ->method('capture')
            ->with(1, ['amount_to_capture' => 0])
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('insufficient funds');

        $this->gateway->capture($request);
    }
}
