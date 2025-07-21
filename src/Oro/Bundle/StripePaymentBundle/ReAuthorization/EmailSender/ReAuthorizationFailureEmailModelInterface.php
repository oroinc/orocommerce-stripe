<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender;

use Oro\Bundle\EmailBundle\Model\EmailHolderInterface;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;

/**
 * Interface for the re-authorization failure email model.
 */
interface ReAuthorizationFailureEmailModelInterface
{
    public function getPaymentTransaction(): PaymentTransaction;

    public function getPaymentMethodResult(): array;

    /**
     * @return array<EmailHolderInterface>
     */
    public function getRecipients(): array;

    public function getEmailTemplateName(): string;
}
