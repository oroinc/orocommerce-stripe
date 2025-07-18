<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Event;

use Oro\Bundle\StripePaymentBundle\Event\StripeCustomerActionBeforeRequestEvent;
use Oro\Bundle\StripePaymentBundle\StripeCustomer\Action\StripeCustomerActionInterface;
use PHPUnit\Framework\TestCase;

final class StripeCustomerActionBeforeRequestEventTest extends TestCase
{
    private StripeCustomerActionInterface $stripeAction;

    private string $requestName;

    private array $requestArgs;

    protected function setUp(): void
    {
        $this->stripeAction = $this->createMock(StripeCustomerActionInterface::class);
        $this->requestName = 'customersSearch';
        $this->requestArgs = ['param1' => 'value1'];
    }

    public function testGetStripeAction(): void
    {
        $event = new StripeCustomerActionBeforeRequestEvent(
            $this->stripeAction,
            $this->requestName,
            $this->requestArgs
        );

        self::assertSame($this->stripeAction, $event->getStripeAction());
    }

    public function testGetRequestName(): void
    {
        $event = new StripeCustomerActionBeforeRequestEvent(
            $this->stripeAction,
            $this->requestName,
            $this->requestArgs
        );

        self::assertSame($this->requestName, $event->getRequestName());
    }

    public function testGetRequestArgs(): void
    {
        $event = new StripeCustomerActionBeforeRequestEvent(
            $this->stripeAction,
            $this->requestName,
            $this->requestArgs
        );

        self::assertSame($this->requestArgs, $event->getRequestArgs());
    }

    public function testSetRequestArgs(): void
    {
        $event = new StripeCustomerActionBeforeRequestEvent(
            $this->stripeAction,
            $this->requestName,
            $this->requestArgs
        );

        $newArgs = ['new_param' => 'new_value'];
        $event->setRequestArgs($newArgs);

        self::assertSame($newArgs, $event->getRequestArgs());
    }
}
