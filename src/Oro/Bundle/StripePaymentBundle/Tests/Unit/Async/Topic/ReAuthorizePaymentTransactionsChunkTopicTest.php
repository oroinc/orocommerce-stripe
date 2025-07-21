<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Async\Topic;

use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsChunkTopic;
use Oro\Component\MessageQueue\Test\AbstractTopicTestCase;
use Oro\Component\MessageQueue\Topic\TopicInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

final class ReAuthorizePaymentTransactionsChunkTopicTest extends AbstractTopicTestCase
{
    protected function getTopic(): TopicInterface
    {
        return new ReAuthorizePaymentTransactionsChunkTopic();
    }

    #[\Override]
    public function validBodyDataProvider(): array
    {
        return [
            'only required parameters' => [
                'body' => ['jobId' => 11, 'paymentTransactions' => [10, 20, 30]],
                'expectedBody' => ['jobId' => 11, 'paymentTransactions' => [10, 20, 30]],
            ],
        ];
    }

    #[\Override]
    public function invalidBodyDataProvider(): array
    {
        return [
            'no paymentTransactions' => [
                'body' => ['jobId' => 11],
                'exceptionClass' => MissingOptionsException::class,
                'exceptionMessage' => '/The required option "paymentTransactions" is missing/',
            ],
            'no jobId' => [
                'body' => ['paymentTransactions' => [10, 20, 30]],
                'exceptionClass' => MissingOptionsException::class,
                'exceptionMessage' => '/The required option "jobId" is missing/',
            ],
            'invalid jobId type' => [
                'body' => ['jobId' => new \stdClass(), 'paymentTransactions' => [10, 20, 30]],
                'exceptionClass' => InvalidOptionsException::class,
                'exceptionMessage' => '/The option "jobId" with value stdClass is expected to be of type "int", '
                    . 'but is of type "stdClass"/',
            ],
            'invalid paymentTransactions type' => [
                'body' => ['jobId' => 11, 'paymentTransactions' => new \stdClass()],
                'exceptionClass' => InvalidOptionsException::class,
                'exceptionMessage' => '/The option "paymentTransactions" with value stdClass is expected to be '
                    . 'of type "int\[\]", but is of type "stdClass"./',
            ],
        ];
    }
}
