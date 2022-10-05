<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventHandler;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\EventHandler\PaymentRefundedEventHandler;
use Oro\Bundle\StripeBundle\Model\ChargeResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentAwareInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use PHPUnit\Framework\TestCase;

class PaymentRefundedEventHandlerTest extends TestCase
{
    /** @var ManagerRegistry|\PHPUnit\Framework\MockObject\MockObject  */
    private $managerRegistry;

    /** @var PaymentTransactionProvider|\PHPUnit\Framework\MockObject\MockObject  */
    private $paymentTransactionProvider;

    /** @var PaymentTransactionRepository|\PHPUnit\Framework\MockObject\MockObject  */
    private $repositoryMock;

    private PaymentRefundedEventHandler $handler;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->repositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $this->managerRegistry->expects($this->any())
            ->method('getRepository')
            ->willReturn($this->repositoryMock);

        $this->handler = new PaymentRefundedEventHandler(
            $this->managerRegistry,
            $this->paymentTransactionProvider
        );
    }

    public function testIsSupportedSuccess()
    {
        $event = new StripeEvent('charge.refunded', 'stripe_1', new PaymentIntentResponse());
        $this->assertTrue($this->handler->isSupported($event));
    }

    public function testEventNotSupported()
    {
        $event = new StripeEvent('payment_intent.canceled', 'stripe_1', new PaymentIntentResponse());
        $this->assertFalse($this->handler->isSupported($event));
    }

    public function testEventContainsUnsupportedResponseObject()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            sprintf('Unexpected response type object. It should be of %s type', PaymentIntentAwareInterface::class)
        );

        $event = new StripeEvent('charge.refunded', 'stripe_1', new PaymentIntentResponse([]));

        $this->handler->handle($event);
    }

    public function testHandleSuccess()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::CAPTURE)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

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

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setActive(true)
            ->setAmount(100.00)
            ->setAction(PaymentMethodInterface::CANCEL)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);

        $this->assertTrue($refundTransaction->isSuccessful());
        $this->assertEquals('ch_1', $refundTransaction->getReference());

        $transactionResponse = $refundTransaction->getResponse();
        $this->assertArrayHasKey('data', $transactionResponse);
        $this->assertArrayHasKey('source', $transactionResponse);
        $this->assertEquals(ResponseObjectInterface::ACTION_SOURCE_MANUALLY, $transactionResponse['source']);
    }

    public function testSuccessRefundWithRefundTransactionsExists()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::CAPTURE)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $refundTransaction1 = new PaymentTransaction();
        $refundTransaction1->setReference('ch_1')
            ->setAction(PaymentMethodInterface::CANCEL)
            ->setResponse([
                'data' => [
                    'id' => 'ch_1',
                    'refunds' => [
                        'data' => [
                            [
                                'id' => 'ref_1',
                                'amount' => 50,
                                'status' => 'succeeded'
                            ]
                        ],
                        'total_count' => 1
                    ],
                    'status' => 'succeeded'
                ]
            ]);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::REFUND
            ])
            ->willReturn([$refundTransaction1]);

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setActive(true)
            ->setAmount(100.00)
            ->setAction(PaymentMethodInterface::CANCEL)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->willReturn($refundTransaction);

        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);

        $this->assertTrue($refundTransaction->isSuccessful());
        $this->assertEquals('ch_1', $refundTransaction->getReference());

        $transactionResponse = $refundTransaction->getResponse();
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
            ->setAction(PaymentMethodInterface::CAPTURE)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $refundTransaction = new PaymentTransaction();
        $refundTransaction->setReference('ch_1')
            ->setSuccessful(true)
            ->setAction(PaymentMethodInterface::REFUND)
            ->setAmount(100)
            ->setResponse([
                'data' => [
                    'id' => 'ch_1',
                    'refunds' => [
                        'data' => [
                            [
                                'id' => 'ref_1',
                                'amount' => 50,
                                'status' => 'succeeded'
                            ],
                            [
                                'id' => 'ref_2',
                                'amount' => 50,
                                'status' => 'succeeded'
                            ]
                        ],
                        'total_count' => 2
                    ],
                    'status' => 'succeeded'
                ]
            ]);

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

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

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
            'refunds' => [
                'data' => [
                    [
                        'id' => 'ref_1',
                        'amount' => 50,
                        'status' => 'succeeded'
                    ],
                    [
                        'id' => 'ref_2',
                        'amount' => 50,
                        'status' => 'succeeded'
                    ]
                ],
                'total_count' => 2
            ],
            'status' => 'succeeded'
        ];

        $responseObject = new ChargeResponse($responseData);
        return new StripeEvent(
            'charge.refunded',
            'stripe_1',
            $responseObject
        );
    }
}
