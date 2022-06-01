<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\StripeClientFactory;
use Oro\Bundle\StripeBundle\Client\StripeGateway;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\ConfirmPaymentAction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Tests\Unit\Utils\SetReflectionPropertyTrait;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\PaymentIntent;

class ConfirmPaymentActionTest extends TestCase
{
    use SetReflectionPropertyTrait;

    private ConfirmPaymentAction $action;

    /** @var StripeGateway|StripeGatewayInterface|\PHPUnit\Framework\MockObject\MockObject  */
    private StripeGatewayInterface $client;

    protected function setUp(): void
    {
        $this->action = new ConfirmPaymentAction(new StripeClientFactory());

        $this->client = $this->createMock(StripeGateway::class);
        $this->setProperty(ConfirmPaymentAction::class, $this->action, 'client', $this->client);
    }

    public function testIsApplicable(): void
    {
        $this->assertFalse($this->action->isApplicable('test'));
        $this->assertTrue($this->action->isApplicable(PaymentActionInterface::CONFIRM_ACTION));
    }

    public function testExecute(): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());

        $charges = new Collection();
        $charges->offsetSet('balance_transaction', 'test');

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
            'charges' => $charges
        ]);

        $response = new PaymentIntentResponse($paymentIntent->toArray());

        $this->client
            ->expects($this->once())
            ->method('confirm')
            ->willReturn($response);

        $response = $this->action->execute(new StripePaymentConfig(), $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertEquals('pi_1', $transaction->getReference());

        $this->assertTrue($response->isSuccessful());
        $this->assertEquals([
            'successful' => true,
        ], $response->prepareResponse());

        $transactionResponseData = $transaction->getResponse();
        $this->assertArrayHasKey('source', $transactionResponseData);
        $this->assertArrayHasKey('paymentIntentId', $transactionResponseData);
        $this->assertArrayHasKey('data', $transactionResponseData);

        $this->assertEquals('Stripe API', $transactionResponseData['source']);
        $this->assertEquals('pi_1', $transactionResponseData['paymentIntentId']);
    }
}
