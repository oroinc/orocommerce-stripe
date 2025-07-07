<?php

namespace Oro\Bundle\StripePaymentBundle\Async;

use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsChunkTopic;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsInitTopic;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider\ReAuthorizePaymentTransactionsProviderInterface;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;

/**
 * Initiates renewal of payment transactions for uncaptured payments that are about to expire.
 */
class ReAuthorizePaymentTransactionsInitProcessor implements MessageProcessorInterface, TopicSubscriberInterface
{
    private int $chunkSize = 10;

    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly MessageProducerInterface $messageProducer,
        private readonly ReAuthorizePaymentTransactionsProviderInterface $reAuthorizePaymentTransactionsProvider
    ) {
    }

    public function setChunkSize(int $chunkSize): void
    {
        $this->chunkSize = $chunkSize;
    }

    #[\Override]
    public static function getSubscribedTopics(): array
    {
        return [ReAuthorizePaymentTransactionsInitTopic::getName()];
    }

    #[\Override]
    public function process(MessageInterface $message, SessionInterface $session): string
    {
        $paymentTransactionIds = $this->reAuthorizePaymentTransactionsProvider->getPaymentTransactionIds();
        $runCallback = fn (JobRunner $jobRunner, Job $job) => $this->doJob($jobRunner, $job, $paymentTransactionIds);
        $result = $this->jobRunner->runUniqueByMessage($message, $runCallback);

        return $result ? self::ACK : self::REJECT;
    }

    /**
     * @param JobRunner $jobRunner
     * @param Job $job
     * @param iterable<int> $paymentTransactionIds
     *
     * @return bool
     */
    private function doJob(JobRunner $jobRunner, Job $job, iterable $paymentTransactionIds): bool
    {
        $totalCount = 0;
        $chunk = [];
        /** @var Job $rootJob */
        $rootJob = $job->getRootJob();
        $rootJobName = $rootJob->getName();

        foreach ($paymentTransactionIds as $paymentTransactionId) {
            $totalCount++;

            $chunk[] = $paymentTransactionId;

            if (($totalCount % $this->chunkSize) === 0) {
                $this->createChildJob($jobRunner, $rootJobName, $totalCount, $chunk);
                $chunk = [];
            }
        }

        if ($chunk) {
            $this->createChildJob($jobRunner, $rootJobName, $totalCount, $chunk);
        }

        return true;
    }

    private function createChildJob(JobRunner $jobRunner, string $rootJobName, int $totalCount, array $chunk): void
    {
        $chunkNumber = (int)ceil($totalCount / $this->chunkSize);

        $jobRunner->createDelayed(
            $this->getChildJobName($rootJobName, $chunkNumber),
            function (JobRunner $jobRunner, Job $child) use ($chunk) {
                $this->messageProducer->send(
                    ReAuthorizePaymentTransactionsChunkTopic::getName(),
                    ['jobId' => $child->getId(), 'paymentTransactions' => $chunk]
                );
            }
        );
    }

    private function getChildJobName(string $rootJobName, int $chunkNumber): string
    {
        return sprintf('%s:chunk:%s', $rootJobName, $chunkNumber);
    }
}
