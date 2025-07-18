<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Operation;

use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Operation\PaymentTransactionReAuthorizeOperation;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\UIBundle\Tools\FlashMessageHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class PaymentTransactionReAuthorizeOperationTest extends TestCase
{
    private PaymentTransactionReAuthorizeOperation $operation;

    private ReAuthorizationExecutorInterface&MockObject $reAuthorizationExecutor;

    private NumberFormatter&MockObject $numberFormatter;

    private FlashMessageHelper&MockObject $flashMessageHelper;

    private PaymentTransaction $paymentTransaction;

    protected function setUp(): void
    {
        $this->reAuthorizationExecutor = $this->createMock(ReAuthorizationExecutorInterface::class);
        $this->numberFormatter = $this->createMock(NumberFormatter::class);
        $this->flashMessageHelper = $this->createMock(FlashMessageHelper::class);

        $this->paymentTransaction = (new PaymentTransaction())
            ->setAmount(100.0)
            ->setCurrency('USD');

        $this->operation = new PaymentTransactionReAuthorizeOperation(
            $this->reAuthorizationExecutor,
            $this->numberFormatter,
            $this->flashMessageHelper
        );
    }

    public function testIsPreConditionAllowedWithValidTransaction(): void
    {
        $actionData = new ActionData(['data' => $this->paymentTransaction]);
        $errors = new ArrayCollection();

        $this->numberFormatter->expects(self::once())
            ->method('formatCurrency')
            ->with($this->paymentTransaction->getAmount(), $this->paymentTransaction->getCurrency())
            ->willReturn('$100.00');

        $this->reAuthorizationExecutor->expects(self::once())
            ->method('isApplicable')
            ->with($this->paymentTransaction)
            ->willReturn(true);

        $result = $this->operation->isPreConditionAllowed($actionData, $errors);

        self::assertTrue($result);
        self::assertEquals('$100.00', $actionData->get('amountWithCurrency'));
        self::assertEmpty($errors);
    }

    public function testIsPreConditionAllowedWithInvalidTransaction(): void
    {
        $actionData = new ActionData(['data' => $this->paymentTransaction]);
        $errors = new ArrayCollection();

        $this->numberFormatter->expects(self::once())
            ->method('formatCurrency')
            ->with($this->paymentTransaction->getAmount(), $this->paymentTransaction->getCurrency())
            ->willReturn('$100.00');

        $this->reAuthorizationExecutor->expects(self::once())
            ->method('isApplicable')
            ->with($this->paymentTransaction)
            ->willReturn(false);

        $result = $this->operation->isPreConditionAllowed($actionData, $errors);

        self::assertFalse($result);
        self::assertEquals('$100.00', $actionData->get('amountWithCurrency'));
        self::assertEmpty($errors);
    }

    public function testIsPreConditionAllowedWithInvalidEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches(
            '/Action entity is expected to be an instance of .*PaymentTransaction, got .*/'
        );

        $actionData = new ActionData(['data' => new \stdClass()]);
        $this->operation->isPreConditionAllowed($actionData);
    }

    public function testExecuteWithSuccessfulReAuthorization(): void
    {
        $actionData = new ActionData([
            'data' => $this->paymentTransaction,
            'amountWithCurrency' => '$100.00',
        ]);

        $result = ['successful' => true];

        $this->reAuthorizationExecutor->expects(self::once())
            ->method('reAuthorizeTransaction')
            ->with($this->paymentTransaction)
            ->willReturn($result);

        $this->flashMessageHelper->expects(self::once())
            ->method('addFlashMessage')
            ->with(
                'success',
                'oro.stripe_payment.operation.re_authorize.confirmation.success',
                ['%amount%' => '$100.00']
            );

        $this->operation->execute($actionData);

        self::assertEquals($result, $actionData->get('result'));
    }

    public function testExecuteWithFailedReAuthorization(): void
    {
        $actionData = new ActionData([
            'data' => $this->paymentTransaction,
            'amountWithCurrency' => '$100.00',
        ]);

        $result = ['successful' => false, 'error' => 'Insufficient funds'];

        $this->reAuthorizationExecutor->expects(self::once())
            ->method('reAuthorizeTransaction')
            ->with($this->paymentTransaction)
            ->willReturn($result);

        $this->flashMessageHelper->expects(self::once())
            ->method('addFlashMessage')
            ->with(
                'error',
                'Insufficient funds',
                ['%amount%' => '$100.00']
            );

        $this->operation->execute($actionData);

        self::assertEquals($result, $actionData->get('result'));
    }

    public function testExecuteWithFailedReAuthorizationAndDefaultError(): void
    {
        $actionData = new ActionData([
            'data' => $this->paymentTransaction,
            'amountWithCurrency' => '$100.00',
        ]);

        $result = ['successful' => false];

        $this->reAuthorizationExecutor->expects(self::once())
            ->method('reAuthorizeTransaction')
            ->with($this->paymentTransaction)
            ->willReturn($result);

        $this->flashMessageHelper->expects(self::once())
            ->method('addFlashMessage')
            ->with(
                'error',
                'oro.stripe_payment.operation.re_authorize.confirmation.error',
                ['%amount%' => '$100.00']
            );

        $this->operation->execute($actionData);

        self::assertEquals($result, $actionData->get('result'));
    }

    public function testExecuteWithInvalidEntity(): void
    {
        $entity = new \stdClass();

        $this->expectExceptionObject(
            new \InvalidArgumentException(
                sprintf(
                    'Action entity is expected to be an instance of %s, got %s',
                    PaymentTransaction::class,
                    get_debug_type($entity)
                )
            )
        );

        $actionData = new ActionData(['data' => $entity]);
        $this->operation->execute($actionData);
    }
}
