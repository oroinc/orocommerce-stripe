<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use LogicException;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\CapturePaymentAction;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\PaymentIntent;

class CapturePaymentActionTest extends TestCase
{
    private StripeGatewayInterface|MockObject $client;
    private PaymentTransactionProvider|MockObject $transactionProvider;
    private CapturePaymentAction $action;

    protected function setUp(): void
    {
        $factory = $this->createMock(StripeGatewayFactoryInterface::class);
        $this->client = $this->createMock(StripeGatewayInterface::class);
        $factory->expects($this->any())
            ->method('create')
            ->willReturn($this->client);
        $this->transactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->action = new CapturePaymentAction($factory, $this->transactionProvider);
    }

    public function testIsApplicable(): void
    {
        $transaction = new PaymentTransaction();
        $this->assertFalse($this->action->isApplicable('test', $transaction));
        $this->assertTrue($this->action->isApplicable(PaymentMethodInterface::CAPTURE, $transaction));
    }

    public function testExecuteException(): void
    {
        $this->expectException(LogicException::class);
        $this->action->execute(new StripePaymentConfig(), new PaymentTransaction());
    }

    public function testExecuteSuccess(): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());
        $transaction->setActive(true);

        $charges = new Collection();
        $charges->offsetSet('balance_transaction', 'test');

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
            'charges' => $charges
        ]);

        $responseObject = new PaymentIntentResponse($paymentIntent->toArray());

        $this->client
            ->expects($this->once())
            ->method('capture')
            ->willReturn($responseObject);

        $this->transactionProvider->expects($this->once())
            ->method('savePaymentTransaction');

        $response = $this->action->execute(new StripePaymentConfig(), $transaction);

        $this->assertFalse($transaction->isActive());
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
