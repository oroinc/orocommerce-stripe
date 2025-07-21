<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEndpoint\Executor;

use Oro\Bundle\StripePaymentBundle\Event\StripeWebhookEndpointActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\DeleteStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\ApiErrorException as StripeApiErrorException;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Service\WebhookEndpointService as StripeWebhookEndpointService;
use Stripe\WebhookEndpoint as StripeWebhookEndpoint;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DeleteStripeActionExecutorTest extends TestCase
{
    private const string WEBHOOK_STRIPE_ID = 'wh_123';

    private DeleteStripeActionExecutor $executor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&StripeClientFactoryInterface $stripeClientFactory;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $this->stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new DeleteStripeActionExecutor(
            $this->stripeClientFactory,
            $this->eventDispatcher
        );

        $this->stripeClient = $this->createMock(LoggingStripeClient::class);
    }

    public function testIsSupportedByActionName(): void
    {
        self::assertTrue($this->executor->isSupportedByActionName(DeleteStripeWebhookEndpointAction::ACTION_NAME));
        self::assertFalse($this->executor->isSupportedByActionName('other_action'));
    }

    public function testIsApplicableForAction(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => self::WEBHOOK_STRIPE_ID,
        ]);
        $stripeAction = new DeleteStripeWebhookEndpointAction($stripeWebhookConfig);

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionWithoutWebhookId(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig();
        $stripeAction = new DeleteStripeWebhookEndpointAction($stripeWebhookConfig);

        $stripeActionResult = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripeWebhookEndpointActionResult(successful: true),
            $stripeActionResult
        );
    }

    public function testExecuteActionSuccessfully(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => self::WEBHOOK_STRIPE_ID,
        ]);
        $stripeAction = new DeleteStripeWebhookEndpointAction($stripeWebhookConfig);

        $this->stripeClientFactory
            ->expects(self::once())
            ->method('createStripeClient')
            ->with($stripeWebhookConfig)
            ->willReturn($this->stripeClient);

        $webhookEndpoint = $this->createMock(StripeWebhookEndpoint::class);

        $this->stripeClient->webhookEndpoints = $this->createMock(StripeWebhookEndpointService::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('delete')
            ->with(self::WEBHOOK_STRIPE_ID)
            ->willReturn($webhookEndpoint);

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripeWebhookEndpointActionBeforeRequestEvent(
                    $stripeAction,
                    'webhookEndpointsDelete',
                    [self::WEBHOOK_STRIPE_ID]
                )
            );

        $stripeActionResult = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripeWebhookEndpointActionResult(successful: true, stripeWebhookEndpoint: $webhookEndpoint),
            $stripeActionResult
        );
    }

    public function testExecuteActionWith404Error(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => self::WEBHOOK_STRIPE_ID,
        ]);
        $stripeAction = new DeleteStripeWebhookEndpointAction($stripeWebhookConfig);

        $this->stripeClientFactory
            ->expects(self::once())
            ->method('createStripeClient')
            ->with($stripeWebhookConfig)
            ->willReturn($this->stripeClient);

        $this->stripeClient->webhookEndpoints = $this->createMock(StripeWebhookEndpointService::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('delete')
            ->with(self::WEBHOOK_STRIPE_ID)
            ->willThrowException(StripeInvalidRequestException::factory('Not found', 404));

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripeWebhookEndpointActionBeforeRequestEvent(
                    $stripeAction,
                    'webhookEndpointsDelete',
                    [self::WEBHOOK_STRIPE_ID]
                )
            );

        $stripeActionResult = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripeWebhookEndpointActionResult(successful: false),
            $stripeActionResult
        );
    }

    public function testExecuteActionWithApiError(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => self::WEBHOOK_STRIPE_ID,
        ]);
        $stripeAction = new DeleteStripeWebhookEndpointAction($stripeWebhookConfig);

        $this->stripeClientFactory
            ->expects(self::once())
            ->method('createStripeClient')
            ->with($stripeWebhookConfig)
            ->willReturn($this->stripeClient);

        $this->stripeClient->webhookEndpoints = $this->createMock(StripeWebhookEndpointService::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('delete')
            ->with(self::WEBHOOK_STRIPE_ID)
            ->willThrowException(StripeInvalidRequestException::factory('Bad request', 400));

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(
                new StripeWebhookEndpointActionBeforeRequestEvent(
                    $stripeAction,
                    'webhookEndpointsDelete',
                    [self::WEBHOOK_STRIPE_ID]
                )
            );

        $this->expectException(StripeApiErrorException::class);
        $this->executor->executeAction($stripeAction);
    }
}
