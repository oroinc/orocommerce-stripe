<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender;

use Oro\Bundle\EmailBundle\Entity\EmailUser;

/**
 * Sends a re-authorization failure email.
 */
interface ReAuthorizationFailureEmailSenderInterface
{
    public function sendEmail(ReAuthorizationFailureEmailModelInterface $reAuthorizationFailureEmailModel): ?EmailUser;
}
