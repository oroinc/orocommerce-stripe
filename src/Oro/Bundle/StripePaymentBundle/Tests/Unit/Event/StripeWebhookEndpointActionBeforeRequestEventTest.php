<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Event;

use Oro\Bundle\StripePaymentBundle\Event\StripeWebhookEndpointActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\StripeWebhookEndpointActionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripeWebhookEndpointActionBeforeRequestEventTest extends TestCase
{
    private StripeWebhookEndpointActionBeforeRequestEvent $event;

    private MockObject&StripeWebhookEndpointActionInterface $stripeAction;

    private string $requestName;

    private array $initialRequestArgs;

    protected function setUp(): void
    {
        $this->stripeAction = $this->createMock(StripeWebhookEndpointActionInterface::class);
        $this->requestName = 'create_webhook_endpoint';
        $this->initialRequestArgs = ['url' => 'https://example.com/webhook', 'enabled_events' => ['*']];

        $this->event = new StripeWebhookEndpointActionBeforeRequestEvent(
            $this->stripeAction,
            $this->requestName,
            $this->initialRequestArgs
        );
    }

    public function testConstructorAndGetters(): void
    {
        self::assertSame($this->stripeAction, $this->event->getStripeAction());
        self::assertSame($this->requestName, $this->event->getRequestName());
        self::assertSame($this->initialRequestArgs, $this->event->getRequestArgs());
    }

    public function testSetRequestArgsWithNewValues(): void
    {
        $newArgs = ['url' => 'https://new.example.com/webhook', 'enabled_events' => ['charge.succeeded']];

        $this->event->setRequestArgs($newArgs);

        self::assertSame($newArgs, $this->event->getRequestArgs());
    }

    public function testSetRequestArgsWithEmptyArray(): void
    {
        $this->event->setRequestArgs([]);

        self::assertSame([], $this->event->getRequestArgs());
    }

    public function testSetRequestArgsWithNullValues(): void
    {
        $argsWithNulls = ['url' => null, 'enabled_events' => null];

        $this->event->setRequestArgs($argsWithNulls);

        self::assertSame($argsWithNulls, $this->event->getRequestArgs());
    }
}
