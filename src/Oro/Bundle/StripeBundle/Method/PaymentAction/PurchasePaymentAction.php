<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Request\PurchaseRequest;
use Oro\Bundle\StripeBundle\Client\Response\PurchaseResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;

/**
 * Handle purchase payment action.
 */
class PurchasePaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $request = new PurchaseRequest($config, $paymentTransaction);
        $responseObject = $this->getClient($config)->purchase($request);

        $action = $this->getAction($config->getPaymentAction());
        $response = new PurchaseResponse($responseObject);

        $paymentTransaction->setAction($action);
        $paymentTransaction->setActive($action === PaymentMethodInterface::AUTHORIZE);

        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());

        return $response;
    }

    public function isApplicable(string $action): bool
    {
        return $action === PaymentMethodInterface::PURCHASE;
    }

    private function getAction(string $action): string
    {
        return StripePaymentActionMapper::getPaymentAction($action);
    }
}
