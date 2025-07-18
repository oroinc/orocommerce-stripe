<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Sender\EmailTemplateSender;
use Oro\Bundle\NotificationBundle\Model\NotificationSettings;

/**
 * Sends a re-authorization failure email.
 */
class ReAuthorizationFailureEmailSender implements ReAuthorizationFailureEmailSenderInterface
{
    public function __construct(
        private readonly NotificationSettings $notificationSettings,
        private readonly EmailTemplateSender $emailTemplateSender,
        private readonly ManagerRegistry $doctrine
    ) {
    }

    #[\Override]
    public function sendEmail(ReAuthorizationFailureEmailModelInterface $reAuthorizationFailureEmailModel): ?EmailUser
    {
        $paymentTransaction = $reAuthorizationFailureEmailModel->getPaymentTransaction();
        $paymentMethodResult = $reAuthorizationFailureEmailModel->getPaymentMethodResult();
        $subjectEntity = $this->doctrine
            ->getRepository($paymentTransaction->getEntityClass())
            ->find($paymentTransaction->getEntityIdentifier());

        return $this->emailTemplateSender->sendEmailTemplate(
            $this->notificationSettings->getSender(),
            $reAuthorizationFailureEmailModel->getRecipients(),
            $reAuthorizationFailureEmailModel->getEmailTemplateName(),
            [
                'entity' => $paymentTransaction,
                'subjectEntity' => $subjectEntity,
                'subjectAmount' => Price::create($paymentTransaction->getAmount(), $paymentTransaction->getCurrency()),
                'errorMessage' => $paymentMethodResult['error'] ?? '',
                'errorDateTime' => $paymentTransaction->getCreatedAt(),
            ]
        );
    }
}
