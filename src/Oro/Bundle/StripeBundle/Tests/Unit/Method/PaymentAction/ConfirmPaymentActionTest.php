<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\ConfirmPaymentAction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\PaymentIntent;

class ConfirmPaymentActionTest extends TestCase
{
    private StripeGatewayInterface|MockObject $client;
    private EntitiesTransactionsProvider|MockObject $entitiesTransactionsProvider;
    private ConfirmPaymentAction $action;

    #[\Override]
    protected function setUp(): void
    {
        $factory = $this->createMock(StripeGatewayFactoryInterface::class);
        $this->client = $this->createMock(StripeGatewayInterface::class);
        $factory->expects($this->any())
            ->method('create')
            ->willReturn($this->client);
        $this->entitiesTransactionsProvider = $this->createMock(EntitiesTransactionsProvider::class);

        $this->action = new ConfirmPaymentAction(
            $factory,
            $this->entitiesTransactionsProvider,
        );
    }

    public function testIsApplicable(): void
    {
        $transaction = new PaymentTransaction();
        $this->assertFalse($this->action->isApplicable('test', $transaction));
        $this->assertTrue($this->action->isApplicable(PaymentActionInterface::CONFIRM_ACTION, $transaction));
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
