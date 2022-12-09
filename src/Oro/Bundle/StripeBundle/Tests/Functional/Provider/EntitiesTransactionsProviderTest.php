<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\Provider;

use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\OrderBundle\Tests\Functional\DataFixtures\LoadOrderUsers;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;
use Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures\LoadOrders;
use Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures\LoadPaymentTransactions;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class EntitiesTransactionsProviderTest extends WebTestCase
{
    private EntitiesTransactionsProvider $provider;

    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());
        $this->loadFixtures([
            LoadOrders::class,
            LoadPaymentTransactions::class
        ]);

        $this->provider = $this->getContainer()->get('oro_stripe.provider.entities_transactions');
    }

    public function testGetTransactionsForMultipleEntities()
    {
        $order = $this->getReference(LoadOrders::MAIN_ORDER);
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setEntityClass(Order::class)
            ->setEntityIdentifier($order->getId())
            ->setPaymentMethod('stripe_1');

        $transactions = $this->provider->getTransactionsForMultipleEntities($paymentTransaction);

        $this->assertNotEmpty($transactions);
        $this->assertCount(3, $transactions);
    }

    public function testGetTransactionsForMultipleEntitiesWithoutSubOrders()
    {
        $order = $this->getReference(LoadOrders::SUB_ORDER_1);
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setEntityClass(Order::class)
            ->setEntityIdentifier($order->getId())
            ->setPaymentMethod('stripe_1');

        $transactions = $this->provider->getTransactionsForMultipleEntities($paymentTransaction);

        $this->assertNotEmpty($transactions);
        $this->assertCount(1, $transactions);
    }

    /**
     * @param object $entity
     * @param $expected
     * @dataProvider getTestHasEntitiesData
     */
    public function testHasEntities($entity, $expected)
    {
        if (is_string($entity)) {
            $entity = $this->getReference($entity);
        }

        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setEntityClass(get_class($entity))
            ->setEntityIdentifier($entity->getId())
            ->setPaymentMethod('stripe_1');

        $this->assertEquals($expected, $this->provider->hasEntities($paymentTransaction));
    }

    public function getTestHasEntitiesData(): array
    {
        return [
            'Order with suborders should return true' => [
                'entity' => LoadOrders::MAIN_ORDER,
                'expected' => true
            ],
            'Order without suborders should return false' => [
                'entity' => LoadOrders::SUB_ORDER_1,
                'expected' => false
            ],
            'Objects of other than Order types should return false' => [
                'entity' => LoadOrderUsers::ORDER_USER_1,
                'expected' => false
            ],
        ];
    }

    public function testGetExpiringAuthorizationTransactionsWithSinglePaymentMethod()
    {
        $paymentMethods = [LoadPaymentTransactions::STRIPE_PAYMENT_METHOD];
        $expiredTransactions = $this->provider->getExpiringAuthorizationTransactions($paymentMethods);

        $expiredTransactionIdentifiers = $this->collectTransactionIdentifiers($expiredTransactions);

        $this->assertCount(1, $expiredTransactionIdentifiers);

        $expectedExpiredTransactionId = $this->getReference(
            LoadPaymentTransactions::EXPIRED_AUTHORIZATION_TRANSACTION_2
        )->getId();

        $this->assertContains($expectedExpiredTransactionId, $expiredTransactionIdentifiers);
    }

    public function testGetExpiringAuthorizationTransactionsWithMultiplePaymentMethods()
    {
        $paymentMethods = [
            LoadPaymentTransactions::STRIPE_PAYMENT_METHOD,
            LoadPaymentTransactions::TEST_PAYMENT_METHOD,
            'payment_method'
        ];

        $expiredTransactions = $this->provider->getExpiringAuthorizationTransactions($paymentMethods);

        $expiredTransactionIdentifiers = $this->collectTransactionIdentifiers($expiredTransactions);

        $this->assertCount(2, $expiredTransactionIdentifiers);

        $expectedExpiredTransactionId1 = $this->getReference(
            LoadPaymentTransactions::EXPIRED_AUTHORIZATION_TRANSACTION_2
        )->getId();
        $expectedExpiredTransactionId2 = $this->getReference(
            LoadPaymentTransactions::EXPIRED_AUTHORIZATION_TRANSACTION_1
        )->getId();

        $this->assertContains($expectedExpiredTransactionId1, $expiredTransactionIdentifiers);
        $this->assertContains($expectedExpiredTransactionId2, $expiredTransactionIdentifiers);
    }

    public function testHasExpiringAuthorizationTransactions()
    {
        $this->assertTrue($this->provider->hasExpiringAuthorizationTransactions());
    }

    private function collectTransactionIdentifiers(\Iterator $iterator): array
    {
        $identifiers = [];

        /** @var PaymentTransaction $value */
        foreach ($iterator as $value) {
            array_push($identifiers, $value->getId());
        }

        return $identifiers;
    }
}
