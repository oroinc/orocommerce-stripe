<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\Config;

/**
 * Interface for re-authorization configuration model.
 */
interface StripeReAuthorizationConfigInterface
{
    /**
     * @return bool True if a payment authorization hold must be re-authorized
     *  when the authorization window is about to expire.
     */
    public function isReAuthorizationEnabled(): bool;

    /**
     * @return array<string> List of email addresses (e.g. ['email@exmaple.org', 'amanda@example.org'])
     */
    public function getReAuthorizationEmail(): array;

    /**
     * @return string Email template name to use for re-authorization notification emails.
     */
    public function getReAuthorizationEmailTemplate(): string;
}
