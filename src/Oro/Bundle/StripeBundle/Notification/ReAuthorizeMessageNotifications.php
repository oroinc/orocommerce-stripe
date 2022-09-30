<?php

namespace Oro\Bundle\StripeBundle\Notification;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatterInterface;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\OrderBundle\Entity\Order;
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
    private DoctrineHelper $doctrineHelper;
    private LocaleSettings $localeSettings;

    public function __construct(
        StripeNotificationManager $notificationManager,
        DateTimeFormatterInterface $dateTimeFormatter,
        NumberFormatter $numberFormatter,
        TranslatorInterface $translator,
        DoctrineHelper $doctrineHelper,
        LocaleSettings $localeSettings
    ) {
        $this->notificationManager = $notificationManager;
        $this->dateTimeFormatter = $dateTimeFormatter;
        $this->numberFormatter = $numberFormatter;
        $this->translator = $translator;
        $this->doctrineHelper = $doctrineHelper;
        $this->localeSettings = $localeSettings;
    }

    public function sendAuthorizationFailed(PaymentTransaction $transaction, array $recipientEmails, string $error = '')
    {
        $identifier = $this->getIdentifier($transaction);
        $timeZone = new \DateTimeZone($this->localeSettings->getTimeZone());

        $messageParams = [
            '%amount%' => $this->numberFormatter->formatCurrency(
                (float)$transaction->getAmount(),
                $transaction->getCurrency()
            ),
            '%order%' => '#' . $identifier,
            '%date%' => $this->dateTimeFormatter->formatDate(new \DateTime('now', $timeZone)),
            '%time%' => $this->dateTimeFormatter->formatTime(new \DateTime('now', $timeZone)),
            '%reason%' => $error
        ];

        $message = $this->translator->trans(self::AUTHORIZATION_FAILED_MESSAGE, $messageParams);

        $subject = $this->translator->trans(self::AUTHORIZATION_FAILED_SUBJECT, [
            '%order%' => '#' . $identifier
        ]);

        foreach ($recipientEmails as $recipientEmail) {
            $this->notificationManager->sendNotification($recipientEmail, $subject, $message);
        }
    }

    /**
     * @param PaymentTransaction $transaction
     * @return mixed|string|null
     */
    private function getIdentifier(PaymentTransaction $transaction)
    {
        $entity = $this->doctrineHelper->getEntity($transaction->getEntityClass(), $transaction->getEntityIdentifier());

        if ($entity instanceof Order) {
            return $entity->getIdentifier();
        }

        return $this->doctrineHelper->getSingleEntityIdentifier($entity);
    }
}
