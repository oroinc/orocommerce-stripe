<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Event;

use Oro\Bundle\StripePaymentBundle\Event\StripePaymentIntentActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class StripePaymentIntentActionBeforeRequestEventTest extends TestCase
{
    private StripePaymentIntentActionBeforeRequestEvent $event;

    private MockObject&StripePaymentIntentActionInterface $stripeAction;

    private string $requestName;

    private array $initialRequestArgs;

    protected function setUp(): void
    {
        $this->stripeAction = $this->createMock(StripePaymentIntentActionInterface::class);
        $this->requestName = 'create_payment_intent';
        $this->initialRequestArgs = ['amount' => 1000, 'currency' => 'USD'];

        $this->event = new StripePaymentIntentActionBeforeRequestEvent(
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

    public function testSetRequestArgs(): void
    {
        $newArgs = ['amount' => 2000, 'currency' => 'EUR', 'metadata' => ['order_id' => 123]];

        $this->event->setRequestArgs($newArgs);

        self::assertSame($newArgs, $this->event->getRequestArgs());
    }

    public function testSetRequestArgsWithEmptyArray(): void
    {
        $this->event->setRequestArgs([]);

        self::assertSame([], $this->event->getRequestArgs());
    }
}
