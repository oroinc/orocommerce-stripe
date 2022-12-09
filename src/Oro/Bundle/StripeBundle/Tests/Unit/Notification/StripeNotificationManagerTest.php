<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Notification;

use Oro\Bundle\EmailBundle\Model\From;
use Oro\Bundle\NotificationBundle\Model\NotificationSettings;
use Oro\Bundle\StripeBundle\Notification\StripeNotificationManager;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Oro\Component\MessageQueue\Transport\Exception\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StripeNotificationManagerTest extends TestCase
{
    private MessageProducerInterface|MockObject $messageProducer;
    private NotificationSettings|MockObject $notificationSettings;
    private LoggerInterface|MockObject $logger;
    private StripeNotificationManager $notificationManager;

    protected function setUp(): void
    {
        $this->messageProducer = $this->createMock(MessageProducerInterface::class);
        $this->notificationSettings = $this->createMock(NotificationSettings::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->notificationManager = new StripeNotificationManager(
            $this->messageProducer,
            $this->notificationSettings,
            $this->logger
        );
    }

    public function testSendNotification()
    {
        $this->notificationSettings->expects($this->once())
            ->method('getSender')
            ->willReturn(From::emailAddress('test_sender@test.com'));

        $this->messageProducer->expects($this->once())
            ->method('send')
            ->with('oro.notification.send_notification_email', [
                'from' => 'test_sender@test.com',
                'toEmail' => 'test_recipient@test.com',
                'subject' => 'Test subject',
                'body' => 'Test message',
                'contentType' => 'text/html'
            ]);

        $this->logger->expects($this->never())
            ->method('critical');

        $this->notificationManager->sendNotification('test_recipient@test.com', 'Test subject', 'Test message');
    }

    public function testSendNotificationFailed()
    {
        $exception = new Exception('Failed to send message');

        $this->notificationSettings->expects($this->once())
            ->method('getSender')
            ->willReturn(From::emailAddress('test_sender@test.com'));

        $this->messageProducer->expects($this->once())
            ->method('send')
            ->with('oro.notification.send_notification_email', [
                'from' => 'test_sender@test.com',
                'toEmail' => 'test_recipient@test.com',
                'subject' => 'Test subject',
                'body' => 'Test message',
                'contentType' => 'text/html'
            ])
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('critical')
            ->with('Failed to send stripe notification email', [
                'message' => 'Failed to send message',
                'exception' => $exception,
                'notificationMessage' => [
                    'from' => 'test_sender@test.com',
                    'toEmail' => 'test_recipient@test.com',
                    'subject' => 'Test subject',
                    'body' => 'Test message',
                    'contentType' => 'text/html'
                ]
            ]);

        $this->notificationManager->sendNotification('test_recipient@test.com', 'Test subject', 'Test message');
    }
}
