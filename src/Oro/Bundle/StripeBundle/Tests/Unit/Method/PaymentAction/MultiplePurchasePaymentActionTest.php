<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Request\CreateCustomerRequest;
use Oro\Bundle\StripeBundle\Client\Request\CreateSetupIntentRequest;
use Oro\Bundle\StripeBundle\Client\Request\Factory\CreateCustomerRequestFactory;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\MultiplePurchasePaymentAction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentActionAbstract;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Stripe\Customer;
use Stripe\SetupIntent;

class MultiplePurchasePaymentActionTest extends MultiPaymentTestCase
{
    private CreateCustomerRequestFactory $createCustomerRequestFactory;

    protected function setUp(): void
    {
        $this->createCustomerRequestFactory = $this->createMock(CreateCustomerRequestFactory::class);
        parent::setUp();
    }

    protected function createAction(): PurchasePaymentActionAbstract
    {
        return new MultiplePurchasePaymentAction(
            $this->factory,
            $this->entitiesTransactionsProvider,
            $this->paymentTransactionProvider,
            $this->createCustomerRequestFactory
        );
    }

    /**
     * @dataProvider applicabilityDataProvider
     */
    public function testIsApplicable(string $action, bool $hasEntities, bool $expected): void
    {
        $transaction = new PaymentTransaction();
        $this->entitiesTransactionsProvider->expects($this->any())
            ->method('hasEntities')
            ->with($transaction)
            ->willReturn($hasEntities);

        $this->assertEquals($expected, $this->action->isApplicable($action, $transaction));
    }

    public function applicabilityDataProvider(): \Generator
    {
        yield ['test', true, false];
        yield ['test', false, false];
        yield [PaymentMethodInterface::PURCHASE, false, false];
        yield [PaymentMethodInterface::PURCHASE, true, true];
    }

    public function testExecute(): void
    {
        $config = new StripePaymentConfig();
        $config->set(StripePaymentConfig::PAYMENT_ACTION, StripePaymentActionMapper::MANUAL);
        $transaction = new PaymentTransaction();

        $this->assertCustomerCreationCalled();
        $this->assertSetupIntentCreationCalled($transaction);

        $subTransaction = new PaymentTransaction();
        $this->assertMultiPurchaseCalls($transaction, [$subTransaction]);

        $response = $this->action->execute($config, $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertSuccessfulMultiPurchaseResponse($response);
        $this->assertSuccessfulSubTransaction($subTransaction);
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
