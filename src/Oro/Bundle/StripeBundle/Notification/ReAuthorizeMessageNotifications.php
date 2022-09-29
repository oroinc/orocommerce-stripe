<?php

namespace Oro\Bundle\StripeBundle\Notification;

use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatterInterface;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Prepare and send notifications related to Re-Authorization process.
 */
class ReAuthorizeMessageNotifications
{
    private const AUTHORIZATION_FAILED_MESSAGE = 'oro.stripe.re-authorize.error.authorization-failed.message';
    private const AUTHORIZATION_FAILED_SUBJECT = 'oro.stripe.re-authorize.error.authorization-failed.subject';

    private StripeNotificationManager $notificationManager;
    private DateTimeFormatterInterface $dateTimeFormatter;
    private NumberFormatter $numberFormatter;
    private TranslatorInterface $translator;

    public function __construct(
        StripeNotificationManager $notificationManager,
        DateTimeFormatterInterface $dateTimeFormatter,
        NumberFormatter $numberFormatter,
        TranslatorInterface $translator
    ) {
        $this->notificationManager = $notificationManager;
        $this->dateTimeFormatter = $dateTimeFormatter;
        $this->numberFormatter = $numberFormatter;
        $this->translator = $translator;
    }

    public function sendAuthorizationFailed(PaymentTransaction $transaction, string $recipientEmail, string $error = '')
    {
        $messageParams = [
            '%amount%' => $this->numberFormatter->formatCurrency(
                (float)$transaction->getAmount(),
                $transaction->getCurrency()
            ),
            '%order%' => '#' . $transaction->getEntityIdentifier(),
            '%date%' => $this->dateTimeFormatter->formatDate(new \DateTime('now')),
            '%time%' => $this->dateTimeFormatter->formatTime(new \DateTime('now')),
            '%reason%' => $error
        ];

        $message = $this->translator->trans(self::AUTHORIZATION_FAILED_MESSAGE, $messageParams);

        $subject = $this->translator->trans(self::AUTHORIZATION_FAILED_SUBJECT, [
            '%order%' => '#' . $transaction->getEntityIdentifier()
        ]);

        $this->notificationManager->sendNotification($recipientEmail, $subject, $message);
    }
}
