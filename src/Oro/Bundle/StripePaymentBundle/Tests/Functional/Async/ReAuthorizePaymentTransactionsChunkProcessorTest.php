<?php

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\Async;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\MessageQueueBundle\Test\Functional\JobsAwareTestTrait;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\Async\ReAuthorizePaymentTransactionsInitProcessor;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsChunkTopic;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsInitTopic;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\Test\StripeClient\MockingStripeClient;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadApplicableReAuthorizationTransactionsData;
use Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures\LoadNonApplicableReAuthorizationTransactionsData;
use Oro\Bundle\TestFrameworkBundle\Mailer\EventListener\MessageLoggerListener;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;
use Stripe\PaymentIntent as StripePaymentIntent;

/**
 * @dbIsolationPerTest
 */
final class ReAuthorizePaymentTransactionsChunkProcessorTest extends WebTestCase
{
    use MessageQueueExtension;
    use JobsAwareTestTrait;

    private ManagerRegistry $doctrine;

    private PaymentTransactionRepository $paymentTransactionRepo;

    private ReAuthorizePaymentTransactionsInitProcessor $processor;

    #[\Override]
    protected function setUp(): void
    {
        $this->initClient();

        $this->doctrine = self::getContainer()->get('doctrine');
        $this->paymentTransactionRepo = self::getContainer()->get('oro_payment.repository.payment_transaction');
        $this->processor = self::getContainer()
            ->get('oro_stripe_payment.async.re_authorize_payment_transactions_init_processor');

        $this->resetTestEnvironment();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->resetTestEnvironment();
        $this->processor->setChunkSize(10);
    }

    private function resetTestEnvironment(): void
    {
        MockingStripeClient::instance()->reset();
        MessageLoggerListener::instance()->reset();
        self::purgeMessageQueue();
        self::clearMessageCollector();
    }

    public function testProcessWhenApplicableAndSuccessful(): void
    {
        $this->processor->setChunkSize(1);
        $this->loadFixtures([LoadApplicableReAuthorizationTransactionsData::class]);

        $this->processInitMessage();

        $sentChunkMessages = self::getSentMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName());
        self::assertCount(2, $sentChunkMessages);

        foreach ($sentChunkMessages as $sentChunkMessage) {
            $paymentTransaction = $this->paymentTransactionRepo->find(
                reset($sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::PAYMENT_TRANSACTIONS])
            );
            $this->assertJobStatus(
                Job::STATUS_NEW,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );

            $this->mockSuccessfulStripeResponses($paymentTransaction);
            $this->processChunkMessage($sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]);

            $this->assertJobStatus(
                Job::STATUS_SUCCESS,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );

            $this->assertPaymentTransactionReauthorized($paymentTransaction);

            self::assertEmailCount(0);
        }
    }

    public function testProcessWhenApplicableAndFailure(): void
    {
        $this->processor->setChunkSize(1);
        $this->loadFixtures([LoadApplicableReAuthorizationTransactionsData::class]);

        $this->processInitMessage();

        $sentChunkMessages = self::getSentMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName());
        self::assertCount(2, $sentChunkMessages);

        foreach ($sentChunkMessages as $sentChunkMessage) {
            $this->assertJobStatus(
                Job::STATUS_NEW,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );

            $this->mockFailedStripeResponse();
            $this->processChunkMessage($sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]);

            $this->assertJobStatus(
                Job::STATUS_SUCCESS,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );

            $this->assertFailureEmailSent('Bad request');
        }
    }

    public function testProcessWhenNotApplicable(): void
    {
        $this->processor->setChunkSize(1);
        $this->loadFixtures([LoadNonApplicableReAuthorizationTransactionsData::class]);

        $this->processInitMessage();

        $sentChunkMessages = self::getSentMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName());
        self::assertCount(3, $sentChunkMessages);

        $paymentTransaction1 = $this->getReference(
            LoadNonApplicableReAuthorizationTransactionsData::TRANSACTION_WITHOUT_CUSTOMER_ID
        );
        $paymentTransaction2 = $this->getReference(
            LoadNonApplicableReAuthorizationTransactionsData::TRANSACTION_WITHOUT_METHOD_ID
        );

        $nonApplicableTransactions = [
            $paymentTransaction1->getId(),
            $paymentTransaction2->getId(),
        ];

        foreach ($sentChunkMessages as $sentChunkMessage) {
            $paymentTransaction = $this->paymentTransactionRepo->find(
                reset($sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::PAYMENT_TRANSACTIONS])
            );
            $this->assertJobStatus(
                Job::STATUS_NEW,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );

            $this->processChunkMessage($sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]);

            $this->assertJobStatus(
                Job::STATUS_SUCCESS,
                $sentChunkMessage[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID]
            );

            if (in_array($paymentTransaction->getId(), $nonApplicableTransactions, true)) {
                $this->assertFailureEmailSent(
                    'Payment method action &quot;re_authorize&quot; is not applicable'
                );
            } else {
                self::assertEmailCount(0);
            }
        }
    }

    private function processInitMessage(): void
    {
        $sentMessage = self::sendMessage(ReAuthorizePaymentTransactionsInitTopic::getName(), []);
        self::consumeMessage($sentMessage);

        $processedMessages = self::getProcessedMessagesByTopic(ReAuthorizePaymentTransactionsInitTopic::getName());
        self::assertCount(1, $processedMessages);

        $message = reset($processedMessages);
        $this->assertMessageProcessedSuccessfully(
            $message,
            'oro_stripe_payment.async.re_authorize_payment_transactions_init_processor'
        );
    }

    private function processChunkMessage(int $jobId): void
    {
        MessageLoggerListener::instance()->reset();
        self::clearProcessedMessages();
        self::consume(1);

        $this->assertJobStatus(Job::STATUS_SUCCESS, $jobId);

        $processedMessages = self::getProcessedMessagesByTopic(ReAuthorizePaymentTransactionsChunkTopic::getName());
        self::assertCount(1, $processedMessages);

        $message = reset($processedMessages);
        $this->assertMessageProcessedSuccessfully(
            $message,
            'oro_stripe_payment.async.re_authorize_payment_transactions_chunk_processor'
        );
    }

    private function assertMessageProcessedSuccessfully(array $message, string $processorServiceId): void
    {
        self::assertProcessedMessageStatus(MessageProcessorInterface::ACK, $message['message']);
        self::assertProcessedMessageProcessor($processorServiceId, $message['message']);
    }

    private function mockSuccessfulStripeResponses(PaymentTransaction $paymentTransaction): void
    {
        // Mock new authorization request
        $stripePaymentIntent = new StripePaymentIntent('pi_123');
        $stripePaymentIntent->status = 'requires_capture';
        $stripePaymentIntent->amount = 12345;
        $stripePaymentIntent->currency = 'usd';
        $stripePaymentIntent->payment_method = $paymentTransaction->getTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_METHOD_ID
        );
        $stripePaymentIntent->customer = $paymentTransaction->getTransactionOption(
            StripePaymentIntentActionInterface::CUSTOMER_ID
        );
        MockingStripeClient::addMockResponse($stripePaymentIntent);

        // Mock old authorization cancel request
        $canceledStripePaymentIntent = new StripePaymentIntent('pi_123');
        $canceledStripePaymentIntent->status = 'canceled';
        $canceledStripePaymentIntent->amount = 12345;
        $canceledStripePaymentIntent->currency = 'usd';
        MockingStripeClient::addMockResponse($canceledStripePaymentIntent);
    }

    private function mockFailedStripeResponse(): void
    {
        MockingStripeClient::addMockResponse(
            StripeInvalidRequestException::factory('Bad request', 400)
        );
    }

    private function assertPaymentTransactionReauthorized(PaymentTransaction $paymentTransaction): void
    {
        $this->doctrine->getManagerForClass(PaymentTransaction::class)->refresh($paymentTransaction);
        self::assertFalse($paymentTransaction->isActive());

        self::assertEquals(3, $paymentTransaction->getRelatedPaymentTransactions()->count());

        $cancelPaymentTransaction = $paymentTransaction
            ->getRelatedPaymentTransactions()
            ->filter(static function (PaymentTransaction $paymentTransaction) {
                return $paymentTransaction->getAction() === PaymentMethodInterface::CANCEL;
            })
            ->first();
        self::assertNotEmpty($cancelPaymentTransaction);
        self::assertTrue($cancelPaymentTransaction->isSuccessful());
        self::assertFalse($cancelPaymentTransaction->isActive());

        $authorizePaymentTransaction = $paymentTransaction->getRelatedPaymentTransactions()
            ->filter(static function (PaymentTransaction $paymentTransaction) {
                return $paymentTransaction->getAction() === PaymentMethodInterface::AUTHORIZE;
            })
            ->first();
        self::assertNotEmpty($authorizePaymentTransaction);
        self::assertTrue($authorizePaymentTransaction->isSuccessful());
        self::assertTrue($authorizePaymentTransaction->isActive());
    }

    private function assertFailureEmailSent(string $expectedReason): void
    {
        self::assertEmailCount(1);

        $mailerMessage = self::getMailerMessage();
        self::assertEmailSubjectContains($mailerMessage, 'Automatic Payment Re-Authorization Failed');
        self::assertEmailHtmlBodyContains(
            $mailerMessage,
            'We have not been able to renew the authorization hold'
        );
        self::assertEmailHtmlBodyContains($mailerMessage, 'Reason: ' . $expectedReason);
    }
}
