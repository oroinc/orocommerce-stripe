<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Request\CreateCustomerRequest;
use Oro\Bundle\StripeBundle\Client\Request\CreateSetupIntentRequest;
use Oro\Bundle\StripeBundle\Client\Request\Factory\CreateCustomerRequestFactory;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentAction;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Stripe\Collection;
use Stripe\Customer;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;

class PurchasePaymentActionTest extends TestCase
{
    private StripeGatewayInterface|MockObject $client;
    private EntitiesTransactionsProvider|MockObject $entitiesTransactionsProvider;
    private PaymentTransactionProvider|MockObject $paymentTransactionProvider;
    private CreateCustomerRequestFactory|MockObject $createCustomerRequestFactory;
    private LoggerInterface|MockObject $logger;

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
        $this->createCustomerRequestFactory = $this->createMock(CreateCustomerRequestFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->action = new PurchasePaymentAction(
            $factory,
            $this->entitiesTransactionsProvider,
            $this->paymentTransactionProvider,
            $this->createCustomerRequestFactory
        );
    }

    public function testIsApplicable(): void
    {
        $transaction = new PaymentTransaction();
        $this->assertFalse($this->action->isApplicable('test', $transaction));
        $this->assertTrue($this->action->isApplicable(PaymentMethodInterface::PURCHASE, $transaction));
    }

    /**
     * @dataProvider getTestExecuteWithoutSetupIntentUsageData
     */
    public function testExecuteWithoutSetupIntentUsage(array $config, string $action, bool $isActive): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());

        $response = $this->createPaymentIntentResponse();

        $this->client
            ->expects($this->once())
            ->method('purchase')
            ->willReturn($response);

        $this->assertCustomerCreationCalled();

        $config = new StripePaymentConfig($config);

        $response = $this->action->execute($config, $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertEquals($isActive, $transaction->isActive());

        $this->assertEquals(
            ['additionalData' => json_encode([
                'customerId' => 'cus001',
                'paymentIntentId' => 'pi_1',
            ])],
            $transaction->getTransactionOptions()
        );
        $this->assertEquals('pi_1', $transaction->getReference());
        $this->assertEquals($action, $transaction->getAction());
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

    public function getTestExecuteWithoutSetupIntentUsageData()
    {
        return [
            [
                'config' => [
                    StripePaymentConfig::PAYMENT_ACTION => 'manual',
                    StripePaymentConfig::ALLOW_RE_AUTHORIZE => false
                ],
                'action' => PaymentMethodInterface::AUTHORIZE,
                'isActive' => true
            ],
            [
                'config' => [
                    StripePaymentConfig::PAYMENT_ACTION => 'automatic',
                    StripePaymentConfig::ALLOW_RE_AUTHORIZE => true
                ],
                'action' => PaymentMethodInterface::CAPTURE,
                'isActive' => false
            ],
            [
                'config' => [
                    StripePaymentConfig::PAYMENT_ACTION => 'automatic',
                    StripePaymentConfig::ALLOW_RE_AUTHORIZE => false
                ],
                'action' => PaymentMethodInterface::CAPTURE,
                'isActive' => false
            ]
        ];
    }

    public function testExecuteWithReAuthorizationEnabled(): void
    {
        $transaction = new PaymentTransaction();
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());

        $response = $this->createPaymentIntentResponse();

        $this->assertCustomerCreationCalled();
        $this->assertSetupIntentCreationCalled($transaction);

        $this->client
            ->expects($this->once())
            ->method('purchase')
            ->willReturn($response);

        $config = new StripePaymentConfig([
            StripePaymentConfig::PAYMENT_ACTION => 'manual',
            StripePaymentConfig::ALLOW_RE_AUTHORIZE => true
        ]);

        $response = $this->action->execute($config, $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertTrue($transaction->isActive());

        $additionalData = json_decode($transaction->getTransactionOptions()['additionalData'], true);

        // Check additional data in transaction options.
        $this->assertNotEmpty($additionalData);
        $this->assertArrayHasKey('customerId', $additionalData);
        $this->assertEquals('cus001', $additionalData['customerId']);
        $this->assertArrayHasKey('setupIntentId', $additionalData);
        $this->assertEquals('sti001', $additionalData['setupIntentId']);
        $this->assertArrayHasKey('paymentIntentId', $additionalData);
        $this->assertEquals('pi_1', $additionalData['paymentIntentId']);

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

    private function createPaymentIntentResponse(): PaymentIntentResponse
    {
        $charges = new Collection();
        $charges->offsetSet('balance_transaction', 'test');

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
            'charges' => $charges
        ]);

        return new PaymentIntentResponse($paymentIntent->toArray());
    }

    private function assertCustomerCreationCalled(): void
    {
        $createCustomerRequest = $this->createMock(CreateCustomerRequest::class);
        $this->createCustomerRequestFactory->expects($this->once())
            ->method('create')
            ->willReturn($createCustomerRequest);

        $customer = Customer::constructFrom([
            'id' => 'cus001',
            'status' => 'succeeded'
        ]);
        $customerResponse = new CustomerResponse($customer->toArray());
        $this->client->expects($this->once())
            ->method('createCustomer')
            ->with($createCustomerRequest)
            ->willReturn($customerResponse);
    }

    private function assertSetupIntentCreationCalled(PaymentTransaction $transaction): void
    {
        $setupIntent = SetupIntent::constructFrom([
            'id' => 'sti001',
            'status' => 'succeeded'
        ]);
        $createSetupIntentRequest = new CreateSetupIntentRequest($transaction);
        $setupIntentResponse = new SetupIntentResponse($setupIntent->toArray());
        $this->client->expects($this->once())
            ->method('createSetupIntent')
            ->with($createSetupIntentRequest)
            ->willReturn($setupIntentResponse);
    }
}
