<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Handle purchase payment action.
 */
class PurchasePaymentAction extends PurchasePaymentActionAbstract implements PaymentActionInterface
{
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        return $this->executePurchase($config, $paymentTransaction);
    }

    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::PURCHASE
            && !$this->entitiesTransactionsProvider->hasEntities($paymentTransaction);
    }
}
