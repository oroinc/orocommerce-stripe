<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Handler;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Handler\ReAuthorizationHandler;
use Oro\Bundle\StripeBundle\Method\Config\Provider\StripePaymentConfigsProvider;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Notification\ReAuthorizeMessageNotifications;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ReAuthorizationHandlerTest extends TestCase
{
    use EntityTrait;

    private EntitiesTransactionsProvider|MockObject $transactionsProvider;
    private PaymentMethodProviderInterface|MockObject $paymentMethodProvider;
    private PaymentTransactionProvider|MockObject $paymentTransactionProvider;
    private StripePaymentConfigsProvider|MockObject $paymentConfigsProvider;
    private ReAuthorizeMessageNotifications|MockObject $messageNotifications;
    private LoggerInterface|MockObject $logger;

    private ReAuthorizationHandler $handler;

    public function setUp(): void
    {
        $this->transactionsProvider = $this->createMock(EntitiesTransactionsProvider::class);
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->paymentConfigsProvider = $this->createMock(StripePaymentConfigsProvider::class);
        $this->messageNotifications = $this->createMock(ReAuthorizeMessageNotifications::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new ReAuthorizationHandler(
            $this->transactionsProvider,
            $this->paymentMethodProvider,
            $this->paymentTransactionProvider,
            $this->paymentConfigsProvider,
            $this->messageNotifications
        );
        $this->handler->setLogger($this->logger);
    }

    public function testSetCancellationReasonException()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported cancellation reason passed');

        $this->handler->setCancellationReason('unsupported_reason');
    }

    public function testReAuthorizeNoConfigs()
    {
        $config = $this->createMock(StripePaymentConfig::class);
        $config->expects($this->any())
            ->method('isReAuthorizationAllowed')
            ->willReturn(false);
        $this->paymentConfigsProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn(['pm1' => $config]);

        $this->transactionsProvider->expects($this->never())
            ->method('getExpiringAuthorizationTransactions');

        $this->handler->reAuthorize();
    }

    public function testReAuthorizeUnableToCancelWithPaymentMethodException()
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $config = $this->createMock(StripePaymentConfig::class);
        $config->expects($this->any())
            ->method('isReAuthorizationAllowed')
            ->willReturn(true);

        $this->paymentConfigsProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn(['pm1' => $config]);

        $authorizeTransaction = $this->getEntity(PaymentTransaction::class, ['id' => 10]);
        $authorizeTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $authorizeTransaction->setAmount(100);
        $authorizeTransaction->setActive(true);
        $authorizeTransaction->setSuccessful(true);
        $authorizeTransaction->setPaymentMethod('pm1');

        $this->transactionsProvider->expects($this->once())
            ->method('getExpiringAuthorizationTransactions')
            ->with(['pm1'])
            ->willReturn(new \ArrayIterator([$authorizeTransaction]));

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('pm1')
            ->willReturn($paymentMethod);

        $cancelTransaction = new PaymentTransaction();
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setAmount(100);
        $cancelTransaction->setSuccessful(false);
        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->with(
                PaymentMethodInterface::CANCEL,
                $authorizeTransaction
            )
            ->willReturn($cancelTransaction);

        $exception = new \RuntimeException('some_exception');
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(
                PaymentMethodInterface::CANCEL,
                $cancelTransaction
            )
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                'Uncaught exception during transaction re-authorization',
                [
                    'exception' => $exception
                ]
            );

        $this->handler->reAuthorize();
    }

    public function testReAuthorizeUnableToCancel()
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $config = $this->createMock(StripePaymentConfig::class);
        $config->expects($this->any())
            ->method('isReAuthorizationAllowed')
            ->willReturn(true);
        $config->expects($this->any())
            ->method('getReAuthorizationErrorEmail')
            ->willReturn(['test@test.com']);

        $this->paymentConfigsProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn(['pm1' => $config]);

        $authorizeTransaction = $this->getEntity(PaymentTransaction::class, ['id' => 10]);
        $authorizeTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $authorizeTransaction->setAmount(100);
        $authorizeTransaction->setActive(true);
        $authorizeTransaction->setSuccessful(true);
        $authorizeTransaction->setPaymentMethod('pm1');

        $this->transactionsProvider->expects($this->once())
            ->method('getExpiringAuthorizationTransactions')
            ->with(['pm1'])
            ->willReturn(new \ArrayIterator([$authorizeTransaction]));

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('pm1')
            ->willReturn($paymentMethod);

        $cancelTransaction = new PaymentTransaction();
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setAmount(100);
        $cancelTransaction->setSuccessful(false);
        $this->paymentTransactionProvider->expects($this->once())
            ->method('createPaymentTransactionByParentTransaction')
            ->with(
                PaymentMethodInterface::CANCEL,
                $authorizeTransaction
            )
            ->willReturn($cancelTransaction);

        $cancelResponse = [
            'error' => 'Remote error'
        ];
        $paymentMethod->expects($this->once())
            ->method('execute')
            ->with(
                PaymentMethodInterface::CANCEL,
                $cancelTransaction
            )
            ->willReturn($cancelResponse);

        $this->paymentTransactionProvider->expects($this->once())
            ->method('savePaymentTransaction')
            ->with($cancelTransaction);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Unable to cancel existing authorization transaction',
                [
                    'transaction' => $cancelTransaction,
                    'response' => $cancelResponse
                ]
            );
        $this->messageNotifications->expects($this->once())
            ->method('sendAuthorizationFailed')->with(
                $cancelTransaction,
                ['test@test.com'],
                'Remote error'
            );

        $this->handler->reAuthorize();
    }

    public function testReAuthorizeUnableToCreateNewAuthorization()
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $config = $this->createMock(StripePaymentConfig::class);
        $config->expects($this->any())
            ->method('isReAuthorizationAllowed')
            ->willReturn(true);
        $config->expects($this->any())
            ->method('getReAuthorizationErrorEmail')
            ->willReturn(['test@test.com']);

        $this->paymentConfigsProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn(['pm1' => $config]);

        $authorizeTransaction = $this->getEntity(PaymentTransaction::class, ['id' => 10]);
        $authorizeTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $authorizeTransaction->setAmount(100);
        $authorizeTransaction->setActive(true);
        $authorizeTransaction->setSuccessful(true);
        $authorizeTransaction->setPaymentMethod('pm1');

        $newAuthorization = (clone $authorizeTransaction)->setSuccessful(false);

        $this->transactionsProvider->expects($this->once())
            ->method('getExpiringAuthorizationTransactions')
            ->with(['pm1'])
            ->willReturn(new \ArrayIterator([$authorizeTransaction]));

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('pm1')
            ->willReturn($paymentMethod);

        $cancelTransaction = $this->getEntity(PaymentTransaction::class, ['id' => 20]);
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setAmount(100);
        $cancelTransaction->setSuccessful(true);
        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $authorizeTransaction],
                [PaymentMethodInterface::AUTHORIZE, $authorizeTransaction],
            )
            ->willReturnOnConsecutiveCalls(
                $cancelTransaction,
                $newAuthorization
            );

        $cancelResponse = ['successful' => true];
        $authorizeResponse = ['error' => 'Remote error'];
        $paymentMethod->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $cancelTransaction],
                [PaymentMethodInterface::AUTHORIZE, $newAuthorization]
            )
            ->willReturnOnConsecutiveCalls(
                $cancelResponse,
                $authorizeResponse
            );

        $this->paymentTransactionProvider->expects($this->exactly(3))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [(clone $authorizeTransaction)->setActive(false)],
                [$newAuthorization]
            );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Unable to create new authorization transaction',
                [
                    'transaction' => $newAuthorization,
                    'response' => $authorizeResponse
                ]
            );
        $this->messageNotifications->expects($this->once())
            ->method('sendAuthorizationFailed')
            ->with(
                $newAuthorization,
                ['test@test.com'],
                'Remote error'
            );

        $this->handler->reAuthorize();
    }

    public function testReAuthorize()
    {
        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $config = $this->createMock(StripePaymentConfig::class);
        $config->expects($this->any())
            ->method('isReAuthorizationAllowed')
            ->willReturn(true);
        $config->expects($this->any())
            ->method('getReAuthorizationErrorEmail')
            ->willReturn(['test@test.com']);

        $this->paymentConfigsProvider->expects($this->once())
            ->method('getConfigs')
            ->willReturn(['pm1' => $config]);

        $authorizeTransaction = $this->getEntity(PaymentTransaction::class, ['id' => 10]);
        $authorizeTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $authorizeTransaction->setAmount(100);
        $authorizeTransaction->setActive(true);
        $authorizeTransaction->setSuccessful(true);
        $authorizeTransaction->setPaymentMethod('pm1');

        $newAuthorization = clone $authorizeTransaction;

        $this->transactionsProvider->expects($this->once())
            ->method('getExpiringAuthorizationTransactions')
            ->with(['pm1'])
            ->willReturn(new \ArrayIterator([$authorizeTransaction]));

        $this->paymentMethodProvider->expects($this->once())
            ->method('getPaymentMethod')
            ->with('pm1')
            ->willReturn($paymentMethod);

        $cancelTransaction = $this->getEntity(PaymentTransaction::class, ['id' => 20]);
        $cancelTransaction->setAction(PaymentMethodInterface::CANCEL);
        $cancelTransaction->setAmount(100);
        $cancelTransaction->setSuccessful(true);
        $this->paymentTransactionProvider->expects($this->exactly(2))
            ->method('createPaymentTransactionByParentTransaction')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $authorizeTransaction],
                [PaymentMethodInterface::AUTHORIZE, $authorizeTransaction],
            )
            ->willReturnOnConsecutiveCalls(
                $cancelTransaction,
                $newAuthorization
            );

        $cancelResponse = ['successful' => true];
        $authorizeResponse = ['successful' => true];
        $paymentMethod->expects($this->exactly(2))
            ->method('execute')
            ->withConsecutive(
                [PaymentMethodInterface::CANCEL, $cancelTransaction],
                [PaymentMethodInterface::AUTHORIZE, $newAuthorization]
            )
            ->willReturnOnConsecutiveCalls(
                $cancelResponse,
                $authorizeResponse
            );

        $this->paymentTransactionProvider->expects($this->exactly(3))
            ->method('savePaymentTransaction')
            ->withConsecutive(
                [$cancelTransaction],
                [(clone $authorizeTransaction)->setActive(false)],
                [$newAuthorization]
            );

        $this->logger->expects($this->never())
            ->method('warning');
        $this->messageNotifications->expects($this->never())
            ->method('sendAuthorizationFailed');

        $this->handler->reAuthorize();
    }
}
