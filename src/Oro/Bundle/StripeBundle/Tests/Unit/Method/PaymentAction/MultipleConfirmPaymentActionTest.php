<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Exception\StripeApiException;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\PaymentAction\MultipleConfirmPaymentAction;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PaymentActionInterface;
use Oro\Bundle\StripeBundle\Method\PaymentAction\PurchasePaymentActionAbstract;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;

class MultipleConfirmPaymentActionTest extends MultiPaymentTestCase
{
    #[\Override]
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

    public function testMultiPurchaseWithErrors()
    {
        $config = new StripePaymentConfig();
        $config->set(StripePaymentConfig::PAYMENT_ACTION, StripePaymentActionMapper::MANUAL);
        $transaction = new PaymentTransaction();
        $transaction->setTransactionOptions(
            ['additionalData' => json_encode([SetupIntentResponse::SETUP_INTENT_ID_PARAM => 'sti001'])]
        );
        $transaction->setSourcePaymentTransaction(new PaymentTransaction());

        $this->assertSetupIntentSearch();

        $subTransaction1 = new PaymentTransaction();
        $subTransaction2 = new PaymentTransaction();

        $this->entitiesTransactionsProvider->expects($this->once())
            ->method('getTransactionsForMultipleEntities')
            ->with($transaction)
            ->willReturn([$subTransaction1, $subTransaction2]);

        $paymentIntent = PaymentIntent::constructFrom([
            'id' => 'pi_1',
            'status' => 'succeeded',
        ]);

        $response = new PaymentIntentResponse($paymentIntent->toArray());

        $this->client->expects($this->exactly(2))
            ->method('purchase')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new StripeApiException('Insufficient funds')),
                $response
            );

        $this->logger->expects($this->once())
            ->method('error');

        $response = $this->action->execute($config, $transaction);

        $this->assertTrue($transaction->isSuccessful());
        $this->assertPartiallySuccessfulMultiPurchaseResponse($response);
    }
}
