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
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\EventHandler\PaymentCanceledEventHandler;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PaymentCanceledEventHandlerTest extends TestCase
{
    private ManagerRegistry|MockObject $managerRegistry;
    private PaymentTransactionProvider|MockObject $paymentTransactionProvider;
    private PaymentTransactionRepository|MockObject $repositoryMock;

    private PaymentCanceledEventHandler $handler;

    protected function setUp(): void
    {
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->repositoryMock = $this->createMock(PaymentTransactionRepository::class);
        $this->managerRegistry->expects($this->any())
            ->method('getRepository')
            ->willReturn($this->repositoryMock);

        $this->handler = new PaymentCanceledEventHandler(
            $this->managerRegistry,
            $this->paymentTransactionProvider
        );
    }

    public function testIsSupportedSuccess()
    {
        $event = new StripeEvent('payment_intent.canceled', new StripePaymentConfig(), new PaymentIntentResponse());
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
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::CANCEL
            ])
            ->willReturn([]);

        $cancelTransaction = new PaymentTransaction();
        $cancelTransaction->setActive(true)
            ->setAmount(100.00)
            ->setAction(PaymentMethodInterface::CANCEL)
            ->setEntityClass(PaymentTransaction::class)
            ->setEntityIdentifier(1);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->willReturn($cancelTransaction);

        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('savePaymentTransaction');

        $event = $this->createEvent();
        $this->handler->handle($event);

        $this->assertTrue($cancelTransaction->isSuccessful());
        $this->assertEquals('pi_1', $cancelTransaction->getReference());

        $transactionResponse = $cancelTransaction->getResponse();
        $this->assertArrayHasKey('data', $transactionResponse);
        $this->assertArrayHasKey('source', $transactionResponse);
        $this->assertEquals(ResponseObjectInterface::ACTION_SOURCE_MANUALLY, $transactionResponse['source']);
        $this->assertFalse($sourceTransaction->isActive());
    }

    public function testPaymentAlreadyCanceled()
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
            ->with([
                'sourcePaymentTransaction' => $sourceTransaction,
                'action' => PaymentMethodInterface::CANCEL
            ])
            ->willReturn([(new PaymentTransaction())->setSuccessful(true)]);

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
            'Unable to cancel transaction: correspond authorized transaction could not be found'
        );

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
            'payment_intent.canceled',
            new StripePaymentConfig([
                AbstractParameterBagPaymentConfig::FIELD_PAYMENT_METHOD_IDENTIFIER => 'stripe_1'
            ]),
            $responseObject
        );
    }
}
