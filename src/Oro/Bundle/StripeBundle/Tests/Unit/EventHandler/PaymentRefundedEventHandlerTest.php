<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventHandler;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\EventHandler\PaymentRefundedEventHandler;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentAwareInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\RefundsCollectionResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentRefundedEventHandlerTest extends TestCase
{
    private ManagerRegistry|MockObject $managerRegistry;
    private PaymentTransactionProvider|MockObject $paymentTransactionProvider;
    private PaymentTransactionRepository|MockObject $repositoryMock;
    private StripeGatewayFactoryInterface|MockObject $stripeClientFactory;

    private PaymentRefundedEventHandler $handler;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->repositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $this->stripeClientFactory = $this->createMock(StripeGatewayFactoryInterface::class);

        $this->managerRegistry->expects($this->any())
            ->method('getRepository')
            ->willReturn($this->repositoryMock);

        $this->handler = new PaymentRefundedEventHandler(
            $this->managerRegistry,
            $this->paymentTransactionProvider,
            $this->stripeClientFactory
        );
    }

    public function testIsSupportedSuccess()
    {
        $event = new StripeEvent('charge.refunded', new StripePaymentConfig(), new PaymentIntentResponse());
        $this->assertTrue($this->handler->isSupported($event));
    }

    public function testEventNotSupported()
    {
        $event = new StripeEvent('payment_intent.canceled', new StripePaymentConfig(), new PaymentIntentResponse());
        $this->assertFalse($this->handler->isSupported($event));
    }

    public function testEventContainsUnsupportedResponseObject()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            sprintf('Unexpected response type object. It should be of %s type', PaymentIntentAwareInterface::class)
        );

        $paymentConfig = new StripePaymentConfig([
            AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_1'
        ]);

        $event = new StripeEvent('charge.refunded', $paymentConfig, new PaymentIntentResponse([]));

        $this->handler->handle($event);
    }

    public function testHandleSuccess()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::CAPTURE);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::REFUND
            ])
            ->willReturn([]);

        $this->assertGetAllRefundsApiCall();

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setActive(true)
            ->setAction(PaymentMethodInterface::REFUND);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);

        $this->assertTrue($refundTransaction->isSuccessful());
        $this->assertEquals('re_2', $refundTransaction->getReference());
        $this->assertEquals('100', $refundTransaction->getAmount());

        $transactionResponse = $refundTransaction->getResponse();
        $this->assertArrayHasKey('data', $transactionResponse);
        $this->assertArrayHasKey('source', $transactionResponse);
        $this->assertEquals(ResponseObjectInterface::ACTION_SOURCE_MANUALLY, $transactionResponse['source']);
    }

    public function testSuccessRefundWithRefundTransactionsExists()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(110.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::CAPTURE);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $refundTransaction1 = new PaymentTransaction();
        $refundTransaction1->setReference('re_1')
            ->setAction(PaymentMethodInterface::REFUND)
            ->setAmount(10.00);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::REFUND
            ])
            ->willReturn([$refundTransaction1]);

        $this->assertGetAllRefundsApiCall();

        $newRefundTransaction = new PaymentTransaction();
        $newRefundTransaction->setActive(true)
            ->setAction(PaymentMethodInterface::REFUND);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->willReturn($newRefundTransaction);

        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);

        $this->assertTrue($newRefundTransaction->isSuccessful());
        $this->assertEquals('re_2', $newRefundTransaction->getReference());
        $this->assertEquals('100', $newRefundTransaction->getAmount());

        $transactionResponse = $newRefundTransaction->getResponse();
        $this->assertArrayHasKey('data', $transactionResponse);
        $this->assertArrayHasKey('source', $transactionResponse);
        $this->assertEquals(ResponseObjectInterface::ACTION_SOURCE_MANUALLY, $transactionResponse['source']);
    }

    public function testRefundPaymentTransactionAlreadyExists()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::CAPTURE);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setReference('re_2')
            ->setSuccessful(true)
            ->setAction(PaymentMethodInterface::REFUND)
            ->setAmount(100.00);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::REFUND
            ])
            ->willReturn([$refundTransaction]);

        $this->assertGetAllRefundsApiCall();

        $this->paymentTransactionProvider->expects($this->never())
            ->method('createPaymentTransactionByParentTransaction');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);
    }

    public function testRefundPaymentTransactionInProcess()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::CAPTURE);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setSuccessful(false)
            ->setActive(true)
            ->setAction(PaymentMethodInterface::REFUND)
            ->setAmount(100.00);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::REFUND
            ])
            ->willReturn([$refundTransaction]);

        $this->paymentTransactionProvider->expects($this->never())
            ->method('createPaymentTransactionByParentTransaction');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);
    }

    public function testSourceTransactionNotExists()
    {
        $this->expectException(StripeEventHandleException::class);
        $this->expectExceptionMessage(
            'Payment could not be refunded. There are no capture transaction'
        );

        $this->assertPaymentTransactionRepository();

        $this->repositoryMock->expects($this->never())
            ->method('findBy');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('createPaymentTransactionByParentTransaction');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);
    }


    public function testSourceAuthorizeTransactionsExists()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::AUTHORIZE);

        $this->assertPaymentTransactionRepository(null, $sourceTransaction);

        $this->repositoryMock->expects($this->never())
            ->method('findBy');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('createPaymentTransactionByParentTransaction');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);
    }

    private function createEvent(): StripeEventInterface
    {
        $responseData = [
            'id' => 'ch_1',
            'object' => 'charge',
            'amount' => 100,
            'amount_captured' => 50,
            'amount_refunded' => 50,
            'balance_transaction' => 'txn_1',
            'billing_details' => [
                'address' => [
                    'city' => 'Test City',
                    'country' => 'Test country'
                ]
            ],
            'captured' => true,
            'currency' => 'usd',
            'created' => 1640181498,
            'failure_code' => 'test code',
            'failure_message' => 'failed',
            'fraud_details' => [],
            'order' => 10,
            'payment_intent' => 'pi_1',
            'payment_method' => 'card_1',
            'status' => 'succeeded'
        ];

        $responseObject = new ChargeResponse($responseData);
        return new StripeEvent(
            'charge.refunded',
            new StripePaymentConfig([
                AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_1'
            ]),
            $responseObject
        );
    }

    private function assertGetAllRefundsApiCall(): void
    {
        $responseData = [
            'data' => [
                [
                    'id' => 're_2',
                    'payment_intent' => 'pi_1',
                    'amount' => 10000,
                    'status' => 'succeeded'
                ]
            ]
        ];

        $allRefundsResponse = new RefundsCollectionResponse($responseData);

        $apiClient = $this->createMock(StripeGatewayInterface::class);
        $apiClient->expects($this->once())
            ->method('getAllRefunds')
            ->willReturn($allRefundsResponse);

        $this->stripeClientFactory->expects($this->once())
            ->method('create')
            ->willReturn($apiClient);
    }

    private function assertPaymentTransactionRepository(
        ?PaymentTransaction $captureTransaction = null,
        ?PaymentTransaction $authorizeTransaction = null,
    ): void {
        $this->repositoryMock->expects($this->any())
            ->method('findOneBy')
            ->willReturnMap([
                [
                    [
                        'reference' => 'pi_1',
                        'action' => PaymentMethodInterface::CAPTURE,
                        'paymentMethod' => 'stripe_1',
                        'successful' => true
                    ],
                    null,
                    $captureTransaction
                ],
                [
                    [
                        'reference' => 'pi_1',
                        'action' => PaymentMethodInterface::AUTHORIZE,
                        'paymentMethod' => 'stripe_1',
                        'successful' => true
                    ],
                    null,
                    $authorizeTransaction
                ]
            ]);
    }
}
