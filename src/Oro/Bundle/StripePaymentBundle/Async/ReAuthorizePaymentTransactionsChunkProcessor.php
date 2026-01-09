<?php

namespace Oro\Bundle\StripePaymentBundle\Async;

use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsChunkTopic;
use Oro\Bundle\StripePaymentBundle\Event\ReAuthorizationFailureEvent;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Component\MessageQueue\Client\TopicSubscriberInterface;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\Job;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Renews a chunk of payment transactions for uncaptured payments that are about to expire.
 */
class ReAuthorizePaymentTransactionsChunkProcessor implements
    MessageProcessorInterface,
    TopicSubscriberInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly PaymentTransactionRepository $paymentTransactionRepository,
        private readonly ReAuthorizationExecutorInterface $reAuthorizationExecutor,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public static function getSubscribedTopics(): array
    {
        return [ReAuthorizePaymentTransactionsChunkTopic::getName()];
    }

    #[\Override]
    public function process(MessageInterface $message, SessionInterface $session): string
    {
        $body = $message->getBody();
        $runCallback = fn (JobRunner $jobRunner, Job $job) => $this->doJob(
            $body[ReAuthorizePaymentTransactionsChunkTopic::PAYMENT_TRANSACTIONS]
        );
        $result = $this->jobRunner->runDelayed($body[ReAuthorizePaymentTransactionsChunkTopic::JOB_ID], $runCallback);

        return $result ? self::ACK : self::REJECT;
    }

    /**
     * @param array<int> $paymentTransactionIds
     *
     * @return bool
     */
    private function doJob(array $paymentTransactionIds): bool
    {
        if (empty($paymentTransactionIds)) {
            return true;
        }
        foreach ($this->paymentTransactionRepository->findBy(['id' => $paymentTransactionIds]) as $paymentTransaction) {
            try {
                if (!$this->reAuthorizationExecutor->isApplicable($paymentTransaction)) {
                    continue;
                }

                $paymentMethodResult = $this->reAuthorizationExecutor->reAuthorizeTransaction($paymentTransaction);
                if (empty($paymentMethodResult['successful'])) {
                    $this->eventDispatcher->dispatch(
                        new ReAuthorizationFailureEvent($paymentTransaction, $paymentMethodResult)
                    );

                    $this->logger->error(
                        'Failed to renew the Stripe payment authorization '
                        . 'for transaction #{paymentTransactionId}: {message}',
                        [
                            'paymentTransactionId' => $paymentTransaction->getId(),
                            'message' => $paymentMethodResult['error'] ?? 'N/A',
                            'paymentMethodResult' => $paymentMethodResult,
                        ]
                    );
                }
            } catch (\Throwable $throwable) {
                $this->eventDispatcher->dispatch(
                    new ReAuthorizationFailureEvent($paymentTransaction, ['successful' => false])
                );

                $this->logger->error(
                    'Failed to renew the Stripe payment authorization '
                    . 'for transaction #{paymentTransactionId}: {message}.',
                    [
                        'paymentTransactionId' => $paymentTransaction->getId(),
                        'message' => $throwable->getMessage(),
                        'throwable' => $throwable,
                    ]
                );
            }
        }

        return true;
    }
}
