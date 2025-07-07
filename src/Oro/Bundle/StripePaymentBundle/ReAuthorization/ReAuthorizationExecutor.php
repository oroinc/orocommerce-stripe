<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;

/**
 * Handles payment re-authorization for payment transaction.
 */
class ReAuthorizationExecutor implements ReAuthorizationExecutorInterface
{
    public function __construct(
        private readonly PaymentMethodProviderInterface $paymentMethodProvider,
        private readonly PaymentTransactionProvider $paymentTransactionProvider,
        private readonly PaymentTransactionRepository $paymentTransactionRepository
    ) {
    }

    #[\Override]
    public function isApplicable(PaymentTransaction $paymentTransaction): bool
    {
        if ($paymentTransaction->getAction() !== PaymentMethodInterface::AUTHORIZE) {
            return false;
        }

        if (!$paymentTransaction->isActive()) {
            return false;
        }

        if (!$paymentTransaction->isSuccessful()) {
            return false;
        }

        if (!$paymentTransaction->getTransactionOption(self::RE_AUTHORIZATION_ENABLED)) {
            return false;
        }

        if ($this->paymentTransactionRepository
            ->hasSuccessfulRelatedTransactionsByAction($paymentTransaction, PaymentMethodInterface::CANCEL)) {
            return false;
        }

        if (!$this->paymentMethodProvider->hasPaymentMethod($paymentTransaction->getPaymentMethod())) {
            return false;
        }

        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentTransaction->getPaymentMethod());
        if (!$paymentMethod->supports(PaymentMethodInterface::RE_AUTHORIZE)) {
            return false;
        }

        return true;
    }

    #[\Override]
    public function reAuthorizeTransaction(PaymentTransaction $paymentTransaction): array
    {
        $reAuthorizePaymentTransaction = $this->paymentTransactionProvider
            ->createPaymentTransactionByParentTransaction(PaymentMethodInterface::RE_AUTHORIZE, $paymentTransaction);

        $paymentMethod = $this->paymentMethodProvider->getPaymentMethod($paymentTransaction->getPaymentMethod());
        $paymentMethodResult = $paymentMethod
            ->execute(PaymentMethodInterface::RE_AUTHORIZE, $reAuthorizePaymentTransaction);

        $this->paymentTransactionProvider->savePaymentTransaction($reAuthorizePaymentTransaction);
        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        return $paymentMethodResult;
    }
}
