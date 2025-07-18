<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\Async\Topic;

use Oro\Bundle\StripePaymentBundle\Async\Topic\ReAuthorizePaymentTransactionsInitTopic;
use Oro\Component\MessageQueue\Test\AbstractTopicTestCase;
use Oro\Component\MessageQueue\Topic\JobAwareTopicInterface;
use Oro\Component\MessageQueue\Topic\TopicInterface;

final class ReAuthorizePaymentTransactionsInitTopicTest extends AbstractTopicTestCase
{
    protected function getTopic(): TopicInterface
    {
        return new ReAuthorizePaymentTransactionsInitTopic();
    }

    public function testImplementsRequiredInterfaces(): void
    {
        self::assertInstanceOf(JobAwareTopicInterface::class, $this->getTopic());
    }

    public function testCreateJobName(): void
    {
        self::assertEquals($this->getTopic()::getName(), $this->getTopic()->createJobName([]));
    }
}
