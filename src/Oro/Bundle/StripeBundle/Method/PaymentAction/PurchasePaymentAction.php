<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;

/**
 * Handle purchase payment action.
 */
class PurchasePaymentAction extends PurchasePaymentActionAbstract implements PaymentActionInterface
{
    #[\Override]
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        /**
         * If re-authorization functionality is enabled setupIntent generated and used to create PaymentIntent. This
         * setupIntent could be used for generating new authorization transaction when current authorization will be
         * expired.
         */
        $client = $this->getClient($config);
        $this->createCustomer($paymentTransaction, $client);

        if ($config->getPaymentAction() === StripePaymentActionMapper::MANUAL && $config->isReAuthorizationAllowed()) {
            $this->createPaymentIntent($paymentTransaction, $config);
        }

        return $this->executePurchase($config, $paymentTransaction);
    }

    #[\Override]
    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::PURCHASE
            && !$this->entitiesTransactionsProvider->hasEntities($paymentTransaction);
    }
}
