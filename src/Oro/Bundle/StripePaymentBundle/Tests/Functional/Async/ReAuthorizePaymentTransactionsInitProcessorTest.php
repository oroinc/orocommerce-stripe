<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\Async;

use Oro\Bundle\MessageQueueBundle\Test\Functional\JobsAwareTestTrait;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\StripePaymentBundle\Async\ReAuthorizePaymentTransactionsInitProcessor;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsChunkTopic;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsInitTopic;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadApplicableReAuthorizationTransactionsData;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadNonApplicableReAuthorizationTransactionsData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;

/**
 * @dbIsolationPerTest
 */
final class ReAuthorizePaymentTransactionsInitProcessorTest extends WebTestCase
{
    use MessageQueueExtension;
    use JobsAwareTestTrait;

    private ReAuthorizePaymentTransactionsInitProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();
        $this->processor = self::getContainer()
            ->get('oro_stripe_payment.async.re_authorize_payment_transactions_init_processor');
        $this->resetTestEnvironment();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->processor->setChunkSize(10);
        $this->resetTestEnvironment();
    }

    private function resetTestEnvironment(): void
    {
        self::purgeMessageQueue();
        self::clearMessageCollector();
    }

    public function testProcessWithNoTransactions(): void
    {
        $this->processInitMessage();

        self::assertEmpty(self::getSentMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName()));
    }

    public function testProcessWithSingleChunk(): void
    {
        $this->loadFixtures([
            LoadApplicableReAuthorizationTransactionsData::class,
            LoadNonApplicableReAuthorizationTransactionsData::class,
        ]);

        $this->processInitMessage();

        $sentChunkMessages = self::getSentMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName());
        self::assertCount(1, $sentChunkMessages);

        $sentChunkMessage = reset($sentChunkMessages);
        $expectedIds = $this->getReAuthorizationTransactionIds();

        self::assertEqualsCanonicalizing(
            $expectedIds,
            $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::PAYMENT_TRANSACTIONS]
        );
        $this->assertJobStatus(Job::STATUS_NEW, $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]);
    }

    public function testProcessWithMultipleChunks(): void
    {
        $this->processor->setChunkSize(1);
        $this->loadFixtures([
            LoadApplicableReAuthorizationTransactionsData::class,
            LoadNonApplicableReAuthorizationTransactionsData::class,
        ]);

        $this->processInitMessage();

        $sentChunkMessages = self::getSentMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName());
        self::assertCount(5, $sentChunkMessages);

        $expectedIds = $this->getReAuthorizationTransactionIds();

        foreach ($sentChunkMessages as $i => $sentChunkMessage) {
            self::assertEquals(
                [$expectedIds[$i]],
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::PAYMENT_TRANSACTIONS]
            );
            $this->assertJobStatus(
                Job::STATUS_NEW,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );
        }
    }

    private function processInitMessage(): void
    {
        $sentMessage = self::sendMessage(ReAuthorizePaymentTransactionsInitTopic::getName(), []);
        self::consumeMessage($sentMessage);

        $processedMessages = self::getProcessedMessagesByTopic(ReAuthorizePaymentTransactionsInitTopic::getName());
        self::assertCount(1, $processedMessages);

        $message = reset($processedMessages);

        self::assertProcessedMessageStatus(MessageProcessorInterface::ACK, $message['message']);
        self::assertProcessedMessageProcessor(
            'oro_stripe_payment.async.re_authorize_payment_transactions_init_processor',
            $message['message']
        );
    }

    private function getReAuthorizationTransactionIds(): array
    {
        $transactionReferences = [
            LoadApplicableReAuthorizationTransactionsData::TRANSACTION_APPLICABLE_1,
            LoadApplicableReAuthorizationTransactionsData::TRANSACTION_APPLICABLE_2,
            LoadNonApplicableReAuthorizationTransactionsData::TRANSACTION_WITHOUT_RE_AUTHORIZATION,
            LoadNonApplicableReAuthorizationTransactionsData::TRANSACTION_WITHOUT_CUSTOMER_ID,
            LoadNonApplicableReAuthorizationTransactionsData::TRANSACTION_WITHOUT_METHOD_ID,
        ];

        $paymentTransactionIds = [];
        foreach ($transactionReferences as $reference) {
            $paymentTransactionIds[] = $this->getReference($reference)->getId();
        }

        return $paymentTransactionIds;
    }
}
