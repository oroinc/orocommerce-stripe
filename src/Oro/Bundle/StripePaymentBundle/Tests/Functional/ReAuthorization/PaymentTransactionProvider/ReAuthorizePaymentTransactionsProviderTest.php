<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\ReAuthorization\PaymentTransactionProvider;

use Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider\ReAuthorizePaymentTransactionsProvider;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadStripePaymentElementChannelData;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadVaryingReAuthorizationTransactionsData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @dbIsolationPerTest
 */
final class ReAuthorizePaymentTransactionsProviderTest extends WebTestCase
{
    private ReAuthorizePaymentTransactionsProvider $provider;

    protected function setUp(): void
    {
        $this->initClient();

        $this->provider = self::getContainer()
            ->get('oro_stripe_payment.re_authorization.payment_transactions_provider.stripe_payment_element');
    }

    public function testGetPaymentTransactionIdsWhenNoPaymentMethods(): void
    {
        $paymentTransactionIds = $this->provider->getPaymentTransactionIds();

        self::assertInstanceOf(\EmptyIterator::class, $paymentTransactionIds);
    }

    public function testGetPaymentTransactionIdsWhenNoPaymentTransactions(): void
    {
        $this->loadFixtures([LoadStripePaymentElementChannelData::class]);

        $paymentTransactionIds = iterator_to_array($this->provider->getPaymentTransactionIds());

        self::assertEmpty($paymentTransactionIds);
    }

    public function testGetPaymentTransactionIds(): void
    {
        $this->loadFixtures([LoadVaryingReAuthorizationTransactionsData::class]);

        $paymentTransactionIds = iterator_to_array($this->provider->getPaymentTransactionIds());

        $eligibleTransaction1 = $this->getReference(
            LoadVaryingReAuthorizationTransactionsData::TRANSACTION_APPLICABLE_1
        );
        $eligibleTransaction2 = $this->getReference(
            LoadVaryingReAuthorizationTransactionsData::TRANSACTION_APPLICABLE_2
        );

        $expectedIds = [
            $eligibleTransaction1->getId(),
            $eligibleTransaction2->getId(),
        ];

        self::assertCount(2, $paymentTransactionIds);
        self::assertEqualsCanonicalizing($expectedIds, $paymentTransactionIds);
    }

    public function testGetPaymentTransactionIdsWithCustomTimeWindow(): void
    {
        $this->loadFixtures([LoadVaryingReAuthorizationTransactionsData::class]);

        // Set custom time window (from 1 to 2 hours ago)
        $this->provider->setCreatedEarlierThan(1);
        $this->provider->setCreatedLaterThan(2);

        $paymentTransactionIds = iterator_to_array($this->provider->getPaymentTransactionIds());

        $eligibleTransaction1 = $this->getReference(
            LoadVaryingReAuthorizationTransactionsData::TRANSACTION_ONE_HOUR
        );

        $expectedIds = [
            $eligibleTransaction1->getId(),
        ];

        self::assertCount(1, $paymentTransactionIds);
        self::assertEqualsCanonicalizing($expectedIds, $paymentTransactionIds);
    }
}
