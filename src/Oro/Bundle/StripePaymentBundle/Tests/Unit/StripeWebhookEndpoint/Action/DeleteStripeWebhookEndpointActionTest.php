<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\StripeWebhookEndpoint\Action;

use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\StripeWebhookEndpoint\Action\DeleteStripeWebhookEndpointAction;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DeleteStripeWebhookEndpointActionTest extends TestCase
{
    private MockObject&StripePaymentElementConfig $stripePaymentElementConfig;

    private DeleteStripeWebhookEndpointAction $action;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfig = $this->createMock(StripePaymentElementConfig::class);

        $this->action = new DeleteStripeWebhookEndpointAction($this->stripePaymentElementConfig);
    }

    public function testGetActionName(): void
    {
        self::assertSame('webhook_endpoint_delete', $this->action->getActionName());
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
