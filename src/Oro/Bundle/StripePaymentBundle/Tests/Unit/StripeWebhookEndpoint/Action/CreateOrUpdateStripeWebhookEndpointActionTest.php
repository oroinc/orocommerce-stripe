<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEndpoint\Action;

use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\CreateOrUpdateStripeWebhookEndpointAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CreateOrUpdateStripeWebhookEndpointActionTest extends TestCase
{
    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private CreateOrUpdateStripeWebhookEndpointAction $action;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);

        $this->action = new CreateOrUpdateStripeWebhookEndpointAction($this->stripePaymentElementConfig);
    }

    public function testGetActionName(): void
    {
        self::assertSame('webhook_endpoint_create_or_update', $this->action->getActionName());
    }

    public function testGetStripeClientConfig(): void
    {
        self::assertSame($this->stripePaymentElementConfig, $this->action->getStripeClientConfig());
    }

    public function testGetStripeWebhookConfig(): void
    {
        self::assertSame($this->stripePaymentElementConfig, $this->action->getStripeWebhookConfig());
    }
}
