<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\CancelPaymentAction;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\PaymentIntent;

class CancelPaymentActionTest extends TestCase
{
    private StripeGatewayInterface|MockObject $client;
    private PaymentTransactionProvider $paymentTransactionProvider;

    private CancelPaymentAction $action;

    protected function setUp(): void
    {
        $factory = $this->createMock(StripeGatewayFactoryInterface::class);
        $this->client = $this->createMock(StripeGatewayInterface::class);
        $factory->expects($this->any())
            ->method('create')
            ->willReturn($this->client);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->action = new CancelPaymentAction(
            $factory,
            $this->paymentTransactionProvider
        );
    }

    public function testIsApplicable(): void
    {
        $transaction = new PaymentTransaction();
        $this->assertFalse($this->action->isApplicable('test', $transaction));
        $this->assertTrue($this->action->isApplicable(PaymentMethodInterface::CANCEL, $transaction));
    }

    public function testExecute(): void
    {
        $sourceTransaction = new PaymentTransaction();
        $sourceTransaction->setAction(PaymentMethodInterface::AUTHORIZE);
        $sourceTransaction->setActive(true);

        $transaction = new PaymentTransaction();
        $transaction->setAction(PaymentMethodInterface::CANCEL);
        $transaction->setSourcePaymentTransaction($sourceTransaction);

        $charges = new Collection();
        $charges->offsetSet('balance_transaction', 'test');

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
            'charges' => $charges
        ]);

        $response = new PaymentIntentResponse($paymentIntent->toArray());

        $this->paymentTransactionProvider->expects($this->once())
            ->method('savePaymentTransaction')
            ->with($transaction);

        $this->client->expects($this->once())
            ->method('cancel')
            ->willReturn($response);

        $response = $this->action->execute(new StripePaymentConfig(), $transaction);

        $this->assertFalse($transaction->isActive());
        $this->assertFalse($sourceTransaction->isActive());

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
