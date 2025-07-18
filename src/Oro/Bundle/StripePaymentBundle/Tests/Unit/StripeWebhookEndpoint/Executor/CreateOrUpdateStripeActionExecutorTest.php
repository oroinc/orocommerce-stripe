<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEndpoint\Executor;

use Oro\Bundle\PaymentBundle\Method\Config\ParameterBag\AbstractParameterBagPaymentConfig;
use Oro\Bundle\StripePaymentBundle\Event\StripeWebhookEndpointActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeClient\LoggingStripeClient;
use Oro\Bundle\StripePaymentBundle\StripeClient\StripeClientFactoryInterface;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\CreateOrUpdateStripeWebhookEndpointAction;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Executor\CreateOrUpdateStripeActionExecutor;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Result\StripeWebhookEndpointActionResult;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\Service\WebhookEndpointService as StripeWebhookEndpointService;
use Stripe\WebhookEndpoint as StripeWebhookEndpoint;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CreateOrUpdateStripeActionExecutorTest extends TestCase
{
    private CreateOrUpdateStripeActionExecutor $executor;

    private MockObject&EventDispatcherInterface $eventDispatcher;

    private MockObject&LoggingStripeClient $stripeClient;

    protected function setUp(): void
    {
        $stripeClientFactory = $this->createMock(StripeClientFactoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->executor = new CreateOrUpdateStripeActionExecutor(
            $stripeClientFactory,
            $this->eventDispatcher
        );

        $this->stripeClient = $this->createMock(LoggingStripeClient::class);
        $stripeClientFactory
            ->method('createStripeClient')
            ->willReturn($this->stripeClient);
    }

    public function testIsSupportedByActionName(): void
    {
        self::assertTrue(
            $this->executor->isSupportedByActionName(CreateOrUpdateStripeWebhookEndpointAction::ACTION_NAME)
        );
        self::assertFalse($this->executor->isSupportedByActionName('unsupported_action'));
    }

    public function testIsApplicableForAction(): void
    {
        $stripeAction = new CreateOrUpdateStripeWebhookEndpointAction(
            $this->createMock(StripePaymentElementConfig::class)
        );

        self::assertTrue($this->executor->isApplicableForAction($stripeAction));
    }

    public function testExecuteActionCreatesWebhookEndpointWhenNoWebhookStripeId(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL => 'Stripe Payment Element',
            StripePaymentElementConfig::WEBHOOK_URL => 'https://example.com/webhook',
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => null,
        ]);

        $stripeAction = new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookConfig);

        $requestArgs = [
            [
                'url' => $stripeWebhookConfig->getWebhookUrl(),
                'enabled_events' => $stripeWebhookConfig->getWebhookEvents(),
                'description' => $stripeWebhookConfig->getWebhookDescription(),
                'metadata' => $stripeWebhookConfig->getWebhookMetadata(),
            ],
        ];

        $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
            $stripeAction,
            'webhookEndpointsCreate',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with($beforeRequestEvent);

        $stripeWebhookEndpoint = $this->createMock(StripeWebhookEndpoint::class);

        $this->stripeClient->webhookEndpoints = $this->createMock(StripeWebhookEndpointService::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('create')
            ->with(...$beforeRequestEvent->getRequestArgs())
            ->willReturn($stripeWebhookEndpoint);

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripeWebhookEndpointActionResult(successful: true, stripeWebhookEndpoint: $stripeWebhookEndpoint),
            $result
        );
        self::assertSame($stripeWebhookEndpoint, $result->getStripeObject());
    }

    public function testExecuteActionUpdatesWebhookEndpointWhenHasWebhookStripeId(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::WEBHOOK_URL => 'https://example.com/webhook',
            AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL => 'Stripe Payment Element',
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => 'wh_123',
        ]);

        $stripeAction = new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookConfig);

        $retrieveBeforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
            $stripeAction,
            'webhookEndpointsRetrieve',
            [$stripeWebhookConfig->getWebhookStripeId()]
        );

        $stripeWebhookEndpoint = $this->createMock(StripeWebhookEndpoint::class);

        $this->stripeClient->webhookEndpoints = $this->createMock(StripeWebhookEndpointService::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('retrieve')
            ->with(...$retrieveBeforeRequestEvent->getRequestArgs())
            ->willReturn($stripeWebhookEndpoint);

        $requestArgs = [
            $stripeWebhookConfig->getWebhookStripeId(),
            [
                'url' => $stripeWebhookConfig->getWebhookUrl(),
                'enabled_events' => $stripeWebhookConfig->getWebhookEvents(),
                'description' => $stripeWebhookConfig->getWebhookDescription(),
                'metadata' => $stripeWebhookConfig->getWebhookMetadata(),
            ],
        ];

        $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
            $stripeAction,
            'webhookEndpointsUpdate',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive([$retrieveBeforeRequestEvent], [$beforeRequestEvent]);

        $stripeWebhookEndpoint = $this->createMock(StripeWebhookEndpoint::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('update')
            ->with(...$beforeRequestEvent->getRequestArgs())
            ->willReturn($stripeWebhookEndpoint);

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripeWebhookEndpointActionResult(successful: true, stripeWebhookEndpoint: $stripeWebhookEndpoint),
            $result
        );
        self::assertSame($stripeWebhookEndpoint, $result->getStripeObject());
    }

    public function testExecuteActionCreatesWebhookEndpointWhenHasWebhookStripeIdButNotFound(): void
    {
        $stripeWebhookConfig = new StripePaymentElementConfig([
            AbstractParameterBagPaymentConfig::FIELD_ADMIN_LABEL => 'Stripe Payment Element',
            StripePaymentElementConfig::WEBHOOK_URL => 'https://example.com/webhook',
            StripePaymentElementConfig::WEBHOOK_STRIPE_ID => 'wh_123',
        ]);

        $stripeAction = new CreateOrUpdateStripeWebhookEndpointAction($stripeWebhookConfig);

        $retrieveBeforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
            $stripeAction,
            'webhookEndpointsRetrieve',
            [$stripeWebhookConfig->getWebhookStripeId()]
        );

        $exception404 = StripeInvalidRequestException::factory('Not found', 404);

        $this->stripeClient->webhookEndpoints = $this->createMock(StripeWebhookEndpointService::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('retrieve')
            ->with(...$retrieveBeforeRequestEvent->getRequestArgs())
            ->willThrowException($exception404);

        $requestArgs = [
            [
                'url' => $stripeWebhookConfig->getWebhookUrl(),
                'enabled_events' => $stripeWebhookConfig->getWebhookEvents(),
                'description' => $stripeWebhookConfig->getWebhookDescription(),
                'metadata' => $stripeWebhookConfig->getWebhookMetadata(),
            ],
        ];

        $beforeRequestEvent = new StripeWebhookEndpointActionBeforeRequestEvent(
            $stripeAction,
            'webhookEndpointsCreate',
            $requestArgs
        );

        $this->eventDispatcher
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->withConsecutive([$retrieveBeforeRequestEvent], [$beforeRequestEvent]);

        $stripeWebhookEndpoint = $this->createMock(StripeWebhookEndpoint::class);
        $this->stripeClient->webhookEndpoints
            ->expects(self::once())
            ->method('create')
            ->with(...$beforeRequestEvent->getRequestArgs())
            ->willReturn($stripeWebhookEndpoint);

        $result = $this->executor->executeAction($stripeAction);

        self::assertEquals(
            new StripeWebhookEndpointActionResult(successful: true, stripeWebhookEndpoint: $stripeWebhookEndpoint),
            $result
        );
        self::assertSame($stripeWebhookEndpoint, $result->getStripeObject());
    }
}
