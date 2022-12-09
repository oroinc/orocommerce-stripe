<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\MultipleConfirmPaymentAction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentActionAbstract;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Stripe\SetupIntent;

class MultipleConfirmPaymentActionTest extends MultiPaymentTestCase
{
    protected function createAction(): PurchasePaymentActionAbstract
    {
        return new MultipleConfirmPaymentAction(
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
        yield [PaymentActionInterface::CONFIRM_ACTION, false, false];
        yield [PaymentActionInterface::CONFIRM_ACTION, true, true];
    }

    public function testExecute(): void
    {
        $config = new StripePaymentConfig();
        $config->set(StripePaymentConfig::PAYMENT_ACTION, StripePaymentActionMapper::MANUAL);
        $transaction = new PaymentTransaction();
        $transaction->setTransactionOptions(
            ['additionalData' => json_encode([SetupIntentResponse::SETUP_INTENT_ID_PARAM => 'sti001'])]
        );
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());

        $this->assertSetupIntentSearch();

        $subTransaction = new PaymentTransaction();
        $this->assertMultiPurchaseCalls($transaction, [$subTransaction]);

        $response = $this->action->execute($config, $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertSuccessfulMultiPurchaseResponse($response);
        $this->assertSuccessfulSubTransaction($subTransaction);
    }

    private function assertSetupIntentSearch(): void
    {
        $setupIntent = SetupIntent::constructFrom([
            'id' => 'sti001',
            'status' => 'succeeded'
        ]);
        $setupIntentResponse = new SetupIntentResponse($setupIntent->toArray());
        $this->client->expects($this->once())
            ->method('findSetupIntent')
            ->with('sti001')
            ->willReturn($setupIntentResponse);
    }
}
