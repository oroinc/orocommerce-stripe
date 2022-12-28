<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\EventHandler;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Event\StripeEvent;
use Oro\Bundle\StripeBundle\Event\StripeEventInterface;
use Oro\Bundle\StripeBundle\EventHandler\PaymentSuccessEventHandler;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentSuccessEventHandlerTest extends TestCase
{
    use EntityTrait;

    private ManagerRegistry|MockObject $managerRegistry;
    private PaymentTransactionProvider|MockObject $paymentTransactionProvider;
    private PaymentTransactionRepository|MockObject $repositoryMock;

    private PaymentSuccessEventHandler $handler;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->repositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $this->managerRegistry->expects($this->any())
            ->method('getRepository')
            ->willReturn($this->repositoryMock);

        $this->handler = new PaymentSuccessEventHandler(
            $this->managerRegistry,
            $this->paymentTransactionProvider
        );
    }

    public function testIsSupportedSuccess()
    {
        $event = new StripeEvent('payment_intent.succeeded', new StripePaymentConfig(), new PaymentIntentResponse());
        $this->assertTrue($this->handler->isSupported($event));
    }

    public function testEventNotSupported()
    {
        $event = new StripeEvent('charge.refunded', new StripePaymentConfig(), new PaymentIntentResponse());
        $this->assertFalse($this->handler->isSupported($event));
    }

    public function testHandleSuccess()
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::AUTHORIZE)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->willReturn([]);

        $captureTransaction = new PaymentTransaction();
        $captureTransaction->setActive(true)
            ->setAmount(100.00)
            ->setAction(PaymentMethodInterface::CAPTURE)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->willReturn($captureTransaction);

        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);

        $this->assertTrue($captureTransaction->isSuccessful());
        $this->assertEquals('pi_1', $captureTransaction->getReference());
        $this->assertFalse($sourceTransaction->isActive());

        $transactionResponse = $captureTransaction->getResponse();
        $this->assertArrayHasKey('data', $transactionResponse);
        $this->assertArrayHasKey('source', $transactionResponse);
        $this->assertEquals(ResponseObjectInterface::ACTION_SOURCE_MANUALLY, $transactionResponse['source']);
    }

    /**
     * @dataProvider getAlreadyCapturedPaymentData
     */
    public function testPaymentAlreadyCaptured($captureTransactions)
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setActive(true)
            ->setAmount(100.00)
            ->setReference('pi_1')
            ->setAction(PaymentMethodInterface::AUTHORIZE)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn($sourceTransaction);

        $this->repositoryMock->expects($this->once())
            ->method('findBy')
            ->willReturn($captureTransactions);

        $this->paymentTransactionProvider->expects($this->never())
            ->method('createPaymentTransactionByParentTransaction');

        $this->paymentTransactionProvider->expects($this->never())
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);
    }

    public function getAlreadyCapturedPaymentData()
    {
        return [
            'Successful Capture transaction exists' => [
                'captureTransactions' => [
                    $this->getEntity(PaymentTransaction::class, [
                        'active' => true,
                        'successful' => true,
                        'action' => 'capture'
                    ]),
                    $this->getEntity(PaymentTransaction::class, [
                        'active' => false,
                        'successful' => false,
                        'action' => 'capture'
                    ])
                ]
            ],
            'Capture payment transaction in process exists' => [
                'captureTransactions' => [
                    $this->getEntity(PaymentTransaction::class, [
                        'active' => true,
                        'successful' => false,
                        'action' => 'capture'
                    ]),
                    $this->getEntity(PaymentTransaction::class, [
                        'active' => false,
                        'successful' => false,
                        'action' => 'capture'
                    ])
                ]
            ]
        ];
    }

    public function testSourceTransactionNotExists()
    {
        $this->repositoryMock->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->repositoryMock->expects($this->never())
            ->method('findSuccessfulRelatedTransactionsByAction');

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
            'id' => 'pi_1',
            'amount' => 100,
            'capture_method' => 'manual',
            'currency' => 'usd',
            'charges' => [],
            'metadata' => [
                'order_id' => 1
            ],
            'next_action' => null,
            'status' => 'succeeded',
        ];

        $responseObject = new PaymentIntentResponse($responseData);
        return new StripeEvent(
            'payment_intent.succeeded',
            new StripePaymentConfig([
                AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_1'
            ]),
            $responseObject
        );
    }
}
