<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;

/**
 * Handles payment re-authorization for payment transactions.
 */
interface ReAuthorizationExecutorInterface
{
    public const string RE_AUTHORIZATION_ENABLED = 'isReAuthorizationEnabled';

    /**
     * @param PaymentTransaction $paymentTransaction The original transaction to check if re-authorization is possible.
     *
     * @return bool
     */
    public function isApplicable(PaymentTransaction $paymentTransaction): bool;

    /**
     * @param PaymentTransaction $paymentTransaction The original transaction to re-authorize.
     *
     * @return array<string,mixed> Payment method execution result. The resulting array has the following structure:
     *  [
     *      'successful' => true,
     *  ]
     */
    public function reAuthorizeTransaction(PaymentTransaction $paymentTransaction): array;
}
