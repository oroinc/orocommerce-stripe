<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEndpoint\Executor;

use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\StripeWebhookEndpointActionExecutorComposite;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\StripeWebhookEndpointActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

final class StripeWebhookEndpointActionExecutorCompositeTest extends TestCase
{
    private StripeWebhookEndpointActionExecutorComposite $executor;

    private MockObject&StripeWebhookEndpointActionExecutorInterface $innerExecutor1;

    private MockObject&StripeWebhookEndpointActionExecutorInterface $innerExecutor2;

    protected function setUp(): void
    {
        $this->innerExecutor1 = $this->createMock(StripeWebhookEndpointActionExecutorInterface::class);
        $this->innerExecutor2 = $this->createMock(StripeWebhookEndpointActionExecutorInterface::class);

        $this->executor = new StripeWebhookEndpointActionExecutorComposite(
            [$this->innerExecutor1, $this->innerExecutor2]
        );
    }

    public function testIsSupportedByActionNameWhenSupported(): void
    {
        $actionName = 'create_endpoint';

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
        $stripeAction = $this->createMock(StripeWebhookEndpointActionInterface::class);

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
        $stripeAction = $this->createMock(StripeWebhookEndpointActionInterface::class);

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
        $stripeAction = $this->createMock(StripeWebhookEndpointActionInterface::class);
        $stripeActionResult = $this->createMock(StripeWebhookEndpointActionResultInterface::class);

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
            ->willReturn($stripeActionResult);

        self::assertSame($stripeActionResult, $this->executor->executeAction($stripeAction));
    }

    public function testExecuteActionWhenNotApplicable(): void
    {
        $stripeAction = $this->createMock(StripeWebhookEndpointActionInterface::class);
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
            sprintf('Action executor "%s" is not applicable', $actionName)
        );

        $this->executor->executeAction($stripeAction);
    }

    public function testEmptyInnerExecutors(): void
    {
        $emptyExecutor = new StripeWebhookEndpointActionExecutorComposite([]);

        $actionName = 'test_action';
        self::assertFalse($emptyExecutor->isSupportedByActionName($actionName));

        $action = $this->createMock(StripeWebhookEndpointActionInterface::class);
        self::assertFalse($emptyExecutor->isApplicableForAction($action));

        $this->expectException(\LogicException::class);
        $emptyExecutor->executeAction($action);
    }

    public function testExecuteHandlesStripeException(): void
    {
        $stripeAction = $this->createMock(StripeWebhookEndpointActionInterface::class);
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

        $result = $this->executor->executeAction($stripeAction);

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
