<?php

namespace Oro\Bundle\StripeBundle\Notification;

use Oro\Bundle\NotificationBundle\Async\Topic\SendEmailNotificationTopic;
use Oro\Bundle\NotificationBundle\Model\NotificationSettings;
use Oro\Component\MessageQueue\Client\MessageProducerInterface;
use Psr\Log\LoggerInterface;

/**
 * Implements logic to prepare and send notifications.
 */
class StripeNotificationManager
{
    private MessageProducerInterface $messageProducer;
    private NotificationSettings $notificationSettings;
    private LoggerInterface $logger;

    public function __construct(
        MessageProducerInterface $messageProducer,
        NotificationSettings $notificationSettings,
        LoggerInterface $logger
    ) {
        $this->messageProducer = $messageProducer;
        $this->notificationSettings = $notificationSettings;
        $this->logger = $logger;
    }

    public function sendNotification(string $recipientEmail, string $subject, string $message)
    {
        $notificationMessage = [
            'from' => $this->notificationSettings->getSender()->toString(),
            'toEmail' => $recipientEmail,
            'subject' => $subject,
            'body' => $message,
            'contentType' => 'text/html'
        ];

        try {
            $this->messageProducer->send(SendEmailNotificationTopic::getName(), $notificationMessage);
        } catch (\Throwable $exception) {
            $this->logger->critical('Failed to send stripe notification email', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
                'notificationMessage' => $notificationMessage
            ]);
        }
    }
}
