<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;

/**
 * Contains the data required to send the re-authorization failure email.
 */
class ReAuthorizationFailureEmailModel implements ReAuthorizationFailureEmailModelInterface
{
    public function __construct(
        private readonly PaymentTransaction $paymentTransaction,
        private readonly array $paymentMethodResult,
        private readonly array $recipients,
        private readonly string $emailTemplateName
    ) {
    }

    #[\Override]
    public function getPaymentTransaction(): PaymentTransaction
    {
        return $this->paymentTransaction;
    }

    #[\Override]
    public function getPaymentMethodResult(): array
    {
        return $this->paymentMethodResult;
    }

    #[\Override]
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    #[\Override]
    public function getEmailTemplateName(): string
    {
        return $this->emailTemplateName;
    }
}
