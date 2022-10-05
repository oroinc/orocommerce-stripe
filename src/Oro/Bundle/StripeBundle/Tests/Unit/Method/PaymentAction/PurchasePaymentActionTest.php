<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentAction;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Stripe\Collection;
use Stripe\PaymentIntent;

class PurchasePaymentActionTest extends TestCase
{
    private StripeGatewayInterface|MockObject $client;
    private EntitiesTransactionsProvider|MockObject $entitiesTransactionsProvider;
    private PaymentTransactionProvider|MockObject $paymentTransactionProvider;

    private PurchasePaymentAction $action;

    protected function setUp(): void
    {
        $factory = $this->createMock(StripeGatewayFactoryInterface::class);
        $this->client = $this->createMock(StripeGatewayInterface::class);
        $factory->expects($this->any())
            ->method('create')
            ->willReturn($this->client);
        $this->entitiesTransactionsProvider = $this->createMock(EntitiesTransactionsProvider::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);

        $this->action = new PurchasePaymentAction(
            $factory,
            $this->entitiesTransactionsProvider,
            $this->paymentTransactionProvider
        );
    }

    public function testIsApplicable(): void
    {
        $transaction = new PaymentTransaction();
        $this->assertFalse($this->action->isApplicable('test', $transaction));
        $this->assertTrue($this->action->isApplicable(PaymentMethodInterface::PURCHASE, $transaction));
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
            ->method('purchase')
            ->willReturn($response);

        $config = new StripePaymentConfig([StripePaymentConfig::PAYMENT_ACTION => 'manual']);
        $response = $this->action->execute($config, $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertTrue($transaction->isActive());

        $this->assertEquals(
            ['additionalData' => json_encode(['paymentIntentId' => 'pi_1'])],
            $transaction->getTransactionOptions()
        );
        $this->assertEquals('pi_1', $transaction->getReference());
        $this->assertEquals(PaymentMethodInterface::AUTHORIZE, $transaction->getAction());
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(
            [
                'successful' => true,
                'requires_action' => false,
                'payment_intent_client_secret' => null
            ],
            $response->prepareResponse()
        );

        $transactionResponseData = $transaction->getResponse();
        $this->assertArrayHasKey('source', $transactionResponseData);
        $this->assertArrayHasKey('paymentIntentId', $transactionResponseData);
        $this->assertArrayHasKey('data', $transactionResponseData);

        $this->assertEquals('Stripe API', $transactionResponseData['source']);
        $this->assertEquals('pi_1', $transactionResponseData['paymentIntentId']);
    }
}
