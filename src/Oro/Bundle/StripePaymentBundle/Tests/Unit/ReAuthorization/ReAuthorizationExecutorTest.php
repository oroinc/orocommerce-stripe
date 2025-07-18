<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\ReAuthorization;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutor;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReAuthorizationExecutorTest extends TestCase
{
    private ReAuthorizationExecutor $executor;

    private MockObject&PaymentMethodProviderInterface $paymentMethodProvider;

    private MockObject&PaymentTransactionProvider $paymentTransactionProvider;

    private MockObject&PaymentTransactionRepository $paymentTransactionRepository;

    protected function setUp(): void
    {
        $this->paymentMethodProvider = $this->createMock(PaymentMethodProviderInterface::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);

        $this->executor = new ReAuthorizationExecutor(
            $this->paymentMethodProvider,
            $this->paymentTransactionProvider,
            $this->paymentTransactionRepository
        );
    }

    public function testIsApplicableWhenActionIsNotAuthorize(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setAction(PaymentMethodInterface::CAPTURE);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableWhenTransactionNotActive(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setActive(false);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableWhenTransactionNotSuccessful(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setSuccessful(false);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableWhenReAuthorizationNotEnabled(): void
    {
        $transaction = $this->createTransaction();
        $transaction->setTransactionOptions([]);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableWhenCancelTransactionExists(): void
    {
        $transaction = $this->createTransaction();

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('hasSuccessfulRelatedTransactionsByAction')
            ->with($transaction, PaymentMethodInterface::CANCEL)
            ->willReturn(true);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableWhenPaymentMethodNotAvailable(): void
    {
        $transaction = $this->createTransaction();

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->with('stripe_payment_element_11')
            ->willReturn(false);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableWhenMethodNotSupports(): void
    {
        $transaction = $this->createTransaction();

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('hasSuccessfulRelatedTransactionsByAction')
            ->willReturn(false);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->willReturn(true);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_payment_element_11')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('supports')
            ->with(PaymentMethodInterface::RE_AUTHORIZE)
            ->willReturn(false);

        self::assertFalse($this->executor->isApplicable($transaction));
    }

    public function testIsApplicableReturnsTrue(): void
    {
        $transaction = $this->createTransaction();

        $this->paymentTransactionRepository
            ->expects(self::once())
            ->method('hasSuccessfulRelatedTransactionsByAction')
            ->willReturn(false);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('hasPaymentMethod')
            ->willReturn(true);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_payment_element_11')
            ->willReturn($paymentMethod);

        $paymentMethod
            ->expects(self::once())
            ->method('supports')
            ->with(PaymentMethodInterface::RE_AUTHORIZE)
            ->willReturn(true);

        self::assertTrue($this->executor->isApplicable($transaction));
    }

    public function testReAuthorizeTransaction(): void
    {
        $transaction = $this->createTransaction();
        $reAuthorizeTransaction = new PaymentTransaction();
        $expectedResult = ['successful' => true];

        $this->paymentTransactionProvider
            ->expects(self::once())
            ->method('createPaymentTransactionByParentTransaction')
            ->with(PaymentMethodInterface::RE_AUTHORIZE, $transaction)
            ->willReturn($reAuthorizeTransaction);

        $paymentMethod = $this->createMock(PaymentMethodInterface::class);
        $paymentMethod->expects(self::once())
            ->method('execute')
            ->with(PaymentMethodInterface::RE_AUTHORIZE, $reAuthorizeTransaction)
            ->willReturn($expectedResult);

        $this->paymentMethodProvider
            ->expects(self::once())
            ->method('getPaymentMethod')
            ->with('stripe_payment_element_11')
            ->willReturn($paymentMethod);

        $this->paymentTransactionProvider
            ->expects(self::exactly(2))
            ->method('savePaymentTransaction')
            ->withConsecutive([$reAuthorizeTransaction], [$transaction]);

        $result = $this->executor->reAuthorizeTransaction($transaction);

        self::assertSame($expectedResult, $result);
    }

    private function createTransaction(): PaymentTransaction
    {
        return (new PaymentTransaction())
            ->setAction(PaymentMethodInterface::AUTHORIZE)
            ->setActive(true)
            ->setSuccessful(true)
            ->setPaymentMethod('stripe_payment_element_11')
            ->setTransactionOptions([ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true]);
    }
}
