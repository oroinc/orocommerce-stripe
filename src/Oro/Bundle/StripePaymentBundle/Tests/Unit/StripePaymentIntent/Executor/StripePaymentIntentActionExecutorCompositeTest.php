<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripePaymentIntent\Executor;

use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorComposite;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

final class StripePaymentIntentActionExecutorCompositeTest extends TestCase
{
    private StripePaymentIntentActionExecutorComposite $executor;

    private MockObject&StripePaymentIntentActionExecutorInterface $innerExecutor1;

    private MockObject&StripePaymentIntentActionExecutorInterface $innerExecutor2;

    protected function setUp(): void
    {
        $this->innerExecutor1 = $this->createMock(StripePaymentIntentActionExecutorInterface::class);
        $this->innerExecutor2 = $this->createMock(StripePaymentIntentActionExecutorInterface::class);

        $this->executor = new StripePaymentIntentActionExecutorComposite(
            [$this->innerExecutor1, $this->innerExecutor2]
        );
    }

    public function testIsSupportedByActionNameWhenSupported(): void
    {
        $actionName = 'test_action';

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with($actionName)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with($actionName)
            ->willReturn(true);

        self::assertTrue($this->executor->isSupportedByActionName($actionName));
    }

    public function testIsSupportedByActionNameWhenNotSupported(): void
    {
        $actionName = 'unsupported_action';

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with($actionName)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isSupportedByActionName')
            ->with($actionName)
            ->willReturn(false);

        self::assertFalse($this->executor->isSupportedByActionName($actionName));
    }

    public function testIsApplicableForActionWhenApplicable(): void
    {
        $stripeAction = $this->createMock(StripePaymentIntentActionInterface::class);

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testIsApplicableForActionWhenNotApplicable(): void
    {
        $stripeAction = $this->createMock(StripePaymentIntentActionInterface::class);

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        self::assertFalse($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionWhenApplicable(): void
    {
        $stripeAction = $this->createMock(StripePaymentIntentActionInterface::class);
        $result = $this->createMock(StripePaymentIntentActionResultInterface::class);

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('executeAction')
            ->with($stripeAction)
            ->willReturn($result);

        self::assertSame($result, $this->executor->executeAction($stripeAction));
    }

    public function testExecuteActionWhenNotApplicable(): void
    {
        $stripeAction = $this->createMock(StripePaymentIntentActionInterface::class);
        $actionName = 'invalid_action';

        $stripeAction
            ->expects(self::once())
            ->method('getActionName')
            ->willReturn($actionName);

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(
            sprintf('Payment method action "%s" is not applicable', $actionName)
        );

        $this->executor->executeAction($stripeAction);
    }

    public function testEmptyInnerExecutors(): void
    {
        $emptyExecutor = new StripePaymentIntentActionExecutorComposite([]);

        $actionName = 'test_action';
        self::assertFalse($emptyExecutor->isSupportedByActionName($actionName));

        $action = $this->createMock(StripePaymentIntentActionInterface::class);
        self::assertFalse($emptyExecutor->isApplicableForAction($action));

        $this->expectException(\LogicException::class);
        $emptyExecutor->executeAction($action);
    }

    public function testExecuteHandlesStripeException(): void
    {
        $stripeAction = $this->createMock(StripePaymentIntentActionInterface::class);
        $errorMessage = 'Stripe error message';
        $stripeException = StripeInvalidRequestException::factory($errorMessage);

        $this->innerExecutor1
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(false);

        $this->innerExecutor2
            ->expects(self::once())
            ->method('isApplicableForAction')
            ->with($stripeAction)
            ->willReturn(true);
        $this->innerExecutor2
            ->expects(self::once())
            ->method('executeAction')
            ->with($stripeAction)
            ->willThrowException($stripeException);

        $composite = new StripePaymentIntentActionExecutorComposite([$this->innerExecutor1, $this->innerExecutor2]);

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
