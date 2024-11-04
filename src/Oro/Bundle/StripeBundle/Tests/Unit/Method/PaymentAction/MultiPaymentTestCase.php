<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Request\Factory\CreateCustomerRequestFactory;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentActionAbstract;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Stripe\PaymentIntent;

abstract class MultiPaymentTestCase extends TestCase
{
    protected StripeGatewayFactoryInterface|MockObject $factory;
    protected StripeGatewayInterface|MockObject $client;
    protected EntitiesTransactionsProvider|MockObject $entitiesTransactionsProvider;
    protected PaymentTransactionProvider|MockObject $paymentTransactionProvider;
    protected CreateCustomerRequestFactory|MockObject $createCustomerRequestFactory;
    protected LoggerInterface|MockObject $logger;
    protected PurchasePaymentActionAbstract $action;

    #[\Override]
    protected function setUp(): void
    {
        $this->factory = $this->createMock(StripeGatewayFactoryInterface::class);
        $this->client = $this->createMock(StripeGatewayInterface::class);
        $this->factory->expects($this->any())
            ->method('create')
            ->willReturn($this->client);
        $this->entitiesTransactionsProvider = $this->createMock(EntitiesTransactionsProvider::class);
        $this->paymentTransactionProvider = $this->createMock(PaymentTransactionProvider::class);
        $this->createCustomerRequestFactory = $this->createMock(CreateCustomerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = $this->createAction();
        $this->action->setLogger($this->logger);
    }

    abstract protected function createAction(): PurchasePaymentActionAbstract;

    protected function assertMultiPurchaseCalls(
        PaymentTransaction $transaction,
        array $subTransactions
    ): void {
        $this->entitiesTransactionsProvider->expects($this->once())
            ->method('getTransactionsForMultipleEntities')
            ->with($transaction)
            ->willReturn($subTransactions);

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
        ]);
        $response = new PaymentIntentResponse($paymentIntent->toArray());
        $this->client->expects($this->exactly(count($subTransactions)))
            ->method('purchase')
            ->willReturn($response);
    }

    protected function assertSuccessfulMultiPurchaseResponse(StripeApiResponseInterface $response): void
    {
        $this->assertTrue($response->isSuccessful());
        $this->assertEquals(
            [
                'successful' => true,
                'is_multi_transaction' => true,
                'has_successful' => true
            ],
            $response->prepareResponse()
        );
    }

    protected function assertPartiallySuccessfulMultiPurchaseResponse(StripeApiResponseInterface $response): void
    {
        $this->assertFalse($response->isSuccessful());
        $this->assertEquals(
            [
                'is_multi_transaction' => true,
                'successful' => false,
                'has_successful' => true
            ],
            $response->prepareResponse()
        );
    }

    protected function assertSuccessfulSubTransaction(PaymentTransaction $subTransaction): void
    {
        $this->assertTrue($subTransaction->isSuccessful());
        $this->assertEquals('pi_1', $subTransaction->getReference());
        $transactionResponseData = $subTransaction->getResponse();
        $this->assertArrayHasKey('source', $transactionResponseData);
        $this->assertArrayHasKey('paymentIntentId', $transactionResponseData);
        $this->assertArrayHasKey('data', $transactionResponseData);

        $this->assertEquals('Stripe API', $transactionResponseData['source']);
        $this->assertEquals('pi_1', $transactionResponseData['paymentIntentId']);
    }

    protected function assertMultiPurchasePartiallySuccessful(
        PaymentTransaction $transaction,
        array $subTransactions
    ): void {
        $this->entitiesTransactionsProvider->expects($this->once())
            ->method('getTransactionsForMultipleEntities')
            ->with($transaction)
            ->willReturn($subTransactions);

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
        ]);

        $response = new PaymentIntentResponse($paymentIntent->toArray());

        $this->client->expects($this->exactly(count($subTransactions)))
            ->method('purchase')
            ->willReturn($response);
    }
}
