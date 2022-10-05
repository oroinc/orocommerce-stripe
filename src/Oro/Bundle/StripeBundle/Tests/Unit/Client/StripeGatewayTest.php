<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Client;

use Oro\Bundle\CustomerBundle\Entity\CustomerUser;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Client\Request\CancelRequest;
use Oro\Bundle\StripeBundle\Client\Request\CaptureRequest;
use Oro\Bundle\StripeBundle\Client\Request\ConfirmRequest;
use Oro\Bundle\StripeBundle\Client\Request\CreateCustomerRequest;
use Oro\Bundle\StripeBundle\Client\Request\CreateSetupIntentRequest;
use Oro\Bundle\StripeBundle\Client\Request\PurchaseRequest;
use Oro\Bundle\StripeBundle\Client\Request\RefundRequest;
use Oro\Bundle\StripeBundle\Client\StripeGateway;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\RefundResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Oro\Bundle\StripeBundle\Tests\Unit\Utils\SetReflectionPropertyTrait;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Customer;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\SearchResult;
use Stripe\Service\CustomerService;
use Stripe\Service\PaymentIntentService;
use Stripe\Service\RefundService;
use Stripe\Service\SetupIntentService;
use Stripe\SetupIntent;
use Stripe\StripeClient;

class StripeGatewayTest extends TestCase
{
    use SetReflectionPropertyTrait;
    use EntityTrait;

    /** @var MockObject|PaymentIntentService */
    private PaymentIntentService $paymentService;

    /** @var SetupIntentService|MockObject */
    private SetupIntentService $setupIntentService;

    /** @var CustomerService|MockObject */
    private CustomerService $customerService;

    /** @var RefundService|MockObject  */
    private RefundService $refundService;

    /** @var MockObject|StripeClient */
    private StripeClient $client;
    private StripeGateway $gateway;

    protected function setUp(): void
    {
        $this->paymentService = $this->createMock(PaymentIntentService::class);
        $this->setupIntentService = $this->createMock(SetupIntentService::class);
        $this->customerService = $this->createMock(CustomerService::class);
        $this->refundService = $this->createMock(RefundService::class);

        $this->client = $this->createMock(StripeClient::class);
        $this->client->paymentIntents = $this->paymentService;
        $this->client->setupIntents = $this->setupIntentService;
        $this->client->customers = $this->customerService;
        $this->client->refunds = $this->refundService;

        $this->gateway = new StripeGateway('test');
        $this->setProperty(StripeGateway::class, $this->gateway, 'client', $this->client);
    }

    public function testPurchaseSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);
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
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);
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
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
            ])
        ]);

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
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
            ])
        ]);

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
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                ConfirmRequest::PAYMENT_INTENT_ID_PARAM => 1
            ])
        ]);

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

    public function testCancelSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);
        $paymentTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);

        $request = new CancelRequest($paymentTransaction);
        $paymentIntent = new PaymentIntent();

        $this->paymentService->expects($this->once())
            ->method('cancel')
            ->with(1, ['cancellation_reason' => 'requested_by_customer'])
            ->willReturn($paymentIntent);

        $expected = new PaymentIntentResponse($paymentIntent->toArray());
        $this->assertEquals($expected, $this->gateway->cancel($request));
    }

    public function testCancelFailed(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);
        $paymentTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);

        $request = new CancelRequest($paymentTransaction);
        $exception = new CardException('insufficient funds');

        $this->paymentService->expects($this->once())
            ->method('cancel')
            ->with(1, ['cancellation_reason' => 'requested_by_customer'])
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('insufficient funds');

        $this->gateway->cancel($request);
    }

    public function testCreateSetupIntentSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);
        $paymentTransaction->setEntityIdentifier(100);
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);

        $request = new CreateSetupIntentRequest($paymentTransaction);
        $setupIntent = new SetupIntent();

        $this->setupIntentService->expects($this->once())
            ->method('create')
            ->with([
                'payment_method' => '1',
                'confirm' => true,
                'metadata' => [
                    'order_id' => 100
                ]
            ])
            ->willReturn($setupIntent);

        $expected = new SetupIntentResponse($setupIntent->toArray());
        $this->assertEquals($expected, $this->gateway->createSetupIntent($request));
    }

    public function testCreateSetupIntentFailed(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);
        $paymentTransaction->setEntityIdentifier(100);
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);

        $request = new CreateSetupIntentRequest($paymentTransaction);
        $exception = new RateLimitException('rate_limit');

        $this->setupIntentService->expects($this->once())
            ->method('create')
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('rate_limit');

        $this->gateway->createSetupIntent($request);
    }

    public function testCreateCustomerExistingSuccess(): void
    {
        $user = $this->getEntity(CustomerUser::class, ['id' => 1]);
        $user->setEmail('test@test.com');

        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setFrontendOwner($user);
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);

        $doctrineHelper = $this->createMock(DoctrineHelper::class);

        $doctrineHelper->expects($this->any())
            ->method('getEntityReference')
            ->willReturn($user);

        $entityNameResolver = $this->createMock(EntityNameResolver::class);
        $request = new CreateCustomerRequest($paymentTransaction, $doctrineHelper, $entityNameResolver);
        $customer = new Customer();

        $searchResult = new SearchResult();
        $searchResult->data = [$customer];
        $this->customerService->expects($this->once())
            ->method('search')
            ->with([
                'query' => "email:'test@test.com'"
            ])
            ->willReturn($searchResult);
        $this->customerService->expects($this->never())
            ->method('create');

        $expected = new CustomerResponse($customer->toArray());
        $this->assertEquals($expected, $this->gateway->createCustomer($request));
    }

    public function testCreateCustomerSuccess(): void
    {
        $user = $this->getEntity(CustomerUser::class, ['id' => 1]);
        $user->setEmail('test@test.com');

        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setFrontendOwner($user);
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);

        $doctrineHelper = $this->createMock(DoctrineHelper::class);

        $doctrineHelper->expects($this->any())
            ->method('getEntityReference')
            ->willReturn($user);

        $entityNameResolver = $this->createMock(EntityNameResolver::class);
        $request = new CreateCustomerRequest($paymentTransaction, $doctrineHelper, $entityNameResolver);
        $customer = new Customer();

        $searchResult = new SearchResult();
        $searchResult->data = [];

        $this->customerService->expects($this->once())
            ->method('search')
            ->with([
                'query' => "email:'test@test.com'"
            ])
            ->willReturn($searchResult);
        $this->customerService->expects($this->once())
            ->method('create')
            ->with([
                'payment_method' => '1',
                'email' => 'test@test.com',
                'name' => null
            ])
            ->willReturn($customer);

        $expected = new CustomerResponse($customer->toArray());
        $this->assertEquals($expected, $this->gateway->createCustomer($request));
    }

    public function testCreateCustomerFail(): void
    {
        $user = $this->getEntity(CustomerUser::class, ['id' => 1]);
        $user->setEmail('test@test.com');

        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setFrontendOwner($user);
        $paymentTransaction->setTransactionOptions([
            'additionalData' => json_encode([
                'stripePaymentMethodId' => 1
            ])
        ]);
        $paymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);

        $doctrineHelper = $this->createMock(DoctrineHelper::class);
        $doctrineHelper->expects($this->any())
            ->method('getEntityReference')
            ->willReturn($user);

        $entityNameResolver = $this->createMock(EntityNameResolver::class);
        $request = new CreateCustomerRequest($paymentTransaction, $doctrineHelper, $entityNameResolver);

        $exception = new RateLimitException('rate_limit');
        $this->customerService->expects($this->once())
            ->method('search')
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('rate_limit');

        $this->gateway->createCustomer($request);
    }

    public function testFindSetupIntentCustomerSuccess(): void
    {
        $setupIntentId = 'sti_001';
        $setupIntent = new SetupIntent();
        $setupIntent->customer = 'cus_001';
        $customer = new Customer();

        $this->setupIntentService->expects($this->once())
            ->method('retrieve')
            ->with($setupIntentId)
            ->willReturn($setupIntent);
        $this->customerService->expects($this->once())
            ->method('retrieve')
            ->with('cus_001')
            ->willReturn($customer);

        $expected = new CustomerResponse($customer->toArray());
        $this->assertEquals($expected, $this->gateway->findSetupIntentCustomer($setupIntentId));
    }

    public function testFindSetupIntentCustomerFailed(): void
    {
        $exception = new RateLimitException('rate_limit');

        $this->setupIntentService->expects($this->once())
            ->method('retrieve')
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('rate_limit');

        $this->gateway->findSetupIntentCustomer('sti_001');
    }

    public function testFindSetupIntentSuccess(): void
    {
        $setupIntentId = 'sti_001';
        $setupIntent = new SetupIntent();

        $this->setupIntentService->expects($this->once())
            ->method('retrieve')
            ->with($setupIntentId)
            ->willReturn($setupIntent);

        $expected = new SetupIntentResponse($setupIntent->toArray());
        $this->assertEquals($expected, $this->gateway->findSetupIntent($setupIntentId));
    }

    public function testFindSetupIntentFailed(): void
    {
        $exception = new RateLimitException('rate_limit');

        $this->setupIntentService->expects($this->once())
            ->method('retrieve')
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('rate_limit');

        $this->gateway->findSetupIntent('sti_001');
    }

    public function testRefundSuccess(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);
        $paymentTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);

        $request = new RefundRequest($paymentTransaction);
        $refund = new Refund();

        $this->refundService->expects($this->once())
            ->method('create')
            ->with([
                'payment_intent' => '1',
                'reason' => 'requested_by_customer'
            ])
            ->willReturn($refund);

        $expected = new RefundResponse($refund->toArray());
        $this->assertEquals($expected, $this->gateway->refund($request));
    }

    public function testRefundFailed(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction = new PaymentTransaction();
        $sourcePaymentTransaction->setResponse([
            PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => 1
        ]);
        $paymentTransaction->setSourcePaymentTransaction($sourcePaymentTransaction);

        $request = new RefundRequest($paymentTransaction);
        $exception = new CardException('insufficient funds');

        $this->refundService->expects($this->once())
            ->method('create')
            ->willThrowException($exception);

        $this->expectException(StripeApiException::class);
        $this->expectExceptionMessage('insufficient funds');

        $this->gateway->refund($request);
    }
}
