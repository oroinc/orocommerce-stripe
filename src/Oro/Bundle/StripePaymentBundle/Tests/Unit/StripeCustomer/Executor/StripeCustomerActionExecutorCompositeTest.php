<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeCustomer\Executor;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\FindOrCreateStripeCustomerAction;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorComposite;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Executor\StripeCustomerActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Result\StripeCustomerActionResultInterface;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

final class StripeCustomerActionExecutorCompositeTest extends TestCase
{
    public function testIsSupportedByActionNameWithNoExecutors(): void
    {
        $composite = new StripeCustomerActionExecutorComposite([]);
        self::assertFalse($composite->isSupportedByActionName('customer_find_or_create'));
    }

    public function testIsSupportedByActionNameWithSingleSupportingExecutor(): void
    {
        $executor = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with('customer_find_or_create')
            ->willReturn(true);

        $composite = new StripeCustomerActionExecutorComposite([$executor]);
        self::assertTrue($composite->isSupportedByActionName('customer_find_or_create'));
    }

    public function testIsSupportedByActionNameWithMultipleExecutors(): void
    {
        $executor1 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor1
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with('customer_find_or_create')
            ->willReturn(false);

        $executor2 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor2
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with('customer_find_or_create')
            ->willReturn(true);

        $composite = new StripeCustomerActionExecutorComposite([$executor1, $executor2]);
        self::assertTrue($composite->isSupportedByActionName('customer_find_or_create'));
    }

    public function testIsApplicableForActionWithNoExecutors(): void
    {
        $stripeAction = new FindOrCreateStripeCustomerAction(
            new StripePaymentElementConfig([]),
            new PaymentTransaction()
        );
        $composite = new StripeCustomerActionExecutorComposite([]);
        self::assertFalse($composite->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionWithSingleApplicableExecutor(): void
    {
        $stripeAction = new FindOrCreateStripeCustomerAction(
            new StripePaymentElementConfig([]),
            new PaymentTransaction()
        );

        $executor = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);

        $composite = new StripeCustomerActionExecutorComposite([$executor]);
        self::assertTrue($composite->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionWithApplicableExecutor(): void
    {
        $stripeAction = new FindOrCreateStripeCustomerAction(
            new StripePaymentElementConfig([]),
            new PaymentTransaction()
        );
        $result = $this->createMock(StripeCustomerActionResultInterface::class);

        $executor1 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $executor2 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);
        $executor2
            ->expects(self::once())
            ->method('executeAction')
            ->with($stripeAction)
            ->willReturn($result);

        $composite = new StripeCustomerActionExecutorComposite([$executor1, $executor2]);
        self::assertSame($result, $composite->executeAction($stripeAction));
    }

    public function testExecuteActionWithNoApplicableExecutor(): void
    {
        $stripeAction = new FindOrCreateStripeCustomerAction(
            new StripePaymentElementConfig([]),
            new PaymentTransaction()
        );

        $executor = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $composite = new StripeCustomerActionExecutorComposite([$executor]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Action executor "customer_find_or_create" is not applicable');

        $composite->executeAction($stripeAction);
    }

    public function testExecuteActionStopsOnFirstApplicableExecutor(): void
    {
        $stripeAction = new FindOrCreateStripeCustomerAction(
            new StripePaymentElementConfig([]),
            new PaymentTransaction()
        );
        $stripeActionResult = $this->createMock(StripeCustomerActionResultInterface::class);

        $executor1 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);
        $executor1
            ->expects(self::once())
            ->method('executeAction')
            ->with($stripeAction)
            ->willReturn($stripeActionResult);

        $executor2 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor2
            ->expects(self::never())
            ->method('isApplicableForAction');

        $composite = new StripeCustomerActionExecutorComposite([$executor1, $executor2]);
        self::assertSame($stripeActionResult, $composite->executeAction($stripeAction));
    }

    public function testExecuteHandlesStripeException(): void
    {
        $stripeAction = $this->createMock(StripeCustomerActionInterface::class);
        $errorMessage = 'Stripe error message';
        $stripeException = StripeInvalidRequestException::factory($errorMessage);

        $executor1 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $executor2 = $this->createMock(StripeCustomerActionExecutorInterface::class);
        $executor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);
        $executor2
            ->expects(self::once())
            ->method('executeAction')
            ->with($stripeAction)
            ->willThrowException($stripeException);

        $composite = new StripeCustomerActionExecutorComposite([$executor1, $executor2]);

        $result = $composite->executeAction($stripeAction);

        self::assertFalse($result->isSuccessful());
        self::assertSame($stripeException, $result->getStripeError());
        self::assertSame(
            [
                'successful' => false,
                'error' => $errorMessage,
            ],
            $result->toArray()
        );
    }
}
