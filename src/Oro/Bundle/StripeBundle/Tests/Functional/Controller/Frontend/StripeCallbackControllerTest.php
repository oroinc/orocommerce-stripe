<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\Controller\Frontend;

use Oro\Bundle\StripeBundle\EventHandler\Exception\NotSupportedEventException;
use Oro\Bundle\StripeBundle\EventHandler\Exception\StripeEventHandleException;
use Oro\Bundle\StripeBundle\EventHandler\StripeWebhookEventHandler;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class StripeCallbackControllerTest extends WebTestCase
{
    /** @var StripeWebhookEventHandler|\PHPUnit\Framework\MockObject\MockObject|null  */
    private $webhookEventHandler;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->initClient();

        $this->webhookEventHandler = $this->createMock(StripeWebhookEventHandler::class);
        $this->getContainer()->set(StripeWebhookEventHandler::class, $this->webhookEventHandler);
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetClient();
    }

    public function testHandleEventsActionSuccess()
    {
        $this->webhookEventHandler->expects($this->once())
            ->method('handleEvent');

        $this->client->request(
            'POST',
            $this->getUrl('oro_stripe_frontend_handle_events'),
            [],
            [],
            [],
            $this->createContent()
        );

        $response = $this->client->getResponse();

        self::assertEquals('', $response->getContent());
        self::assertHtmlResponseStatusCodeEquals($response, 200);
    }

    public function testWithNotSupportedEvent()
    {
        $this->webhookEventHandler->expects($this->once())
            ->method('handleEvent')
            ->willThrowException(
                new NotSupportedEventException('Event is not supported')
            );

        $this->client->request(
            'POST',
            $this->getUrl('oro_stripe_frontend_handle_events'),
            [],
            [],
            [],
            $this->createContent()
        );

        $response = $this->client->getResponse();

        self::assertEquals('Event is not supported', $response->getContent());
        self::assertHtmlResponseStatusCodeEquals($response, 200);
    }

    public function testHandleEventsActionReturnBadRequest()
    {
        $this->webhookEventHandler->expects($this->once())
            ->method('handleEvent')
            ->willThrowException(
                new StripeEventHandleException('Payment could not be refunded. There are no capture transaction')
            );

        $this->client->request(
            'POST',
            $this->getUrl('oro_stripe_frontend_handle_events'),
            [],
            [],
            [],
            $this->createContent()
        );

        $response = $this->client->getResponse();

        self::assertEquals(
            'Payment could not be refunded. There are no capture transaction',
            $response->getContent()
        );
        self::assertHtmlResponseStatusCodeEquals($response, 400);
    }

    public function testHandleEventsActionReturnInternalError()
    {
        $this->webhookEventHandler->expects($this->once())
            ->method('handleEvent')
            ->willThrowException(new \Exception('Invalid response object provided'));

        $this->client->request(
            'POST',
            $this->getUrl('oro_stripe_frontend_handle_events'),
            [],
            [],
            [],
            $this->createContent()
        );

        $response = $this->client->getResponse();

        self::assertEquals(
            'Error occurs during Stripe event processing',
            $response->getContent()
        );
        self::assertHtmlResponseStatusCodeEquals($response, 500);
    }

    public function testHandleEventsActionWithEmptyContent()
    {
        $this->webhookEventHandler->expects($this->never())
            ->method('handleEvent');

        $this->client->request(
            'POST',
            $this->getUrl('oro_stripe_frontend_handle_events')
        );

        $response = $this->client->getResponse();

        self::assertEquals('Request content is empty', $response->getContent());
        self::assertHtmlResponseStatusCodeEquals($response, 400);
    }

    private function createContent(): string
    {
        return json_encode([
            'id' => 'evt_1',
            'object' => 'event',
            'created' => '1650546324',
            'data' => [
                'object' => [
                    'id' => 'ch_1',
                    'object' => 'charge',
                    'amount' => 1498,
                    'captured' => 'true',
                    'payment_intent' => 'pi_1',
                    'status' => 'succeeded'
                ]
            ],
            'type' => 'charge.refunded'
        ], JSON_THROW_ON_ERROR);
    }
}
