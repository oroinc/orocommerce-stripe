<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Request\AuthorizeRequest;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Handle authorization payment actions.
 */
class AuthorizePaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    /**
     * {@inheritdoc}
     */
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $paymentTransaction->setActive(true);

        $request = new AuthorizeRequest($config, $paymentTransaction);
        $responseObject = $this->getClient($config)->purchase($request);
        $response = new StripeApiResponse($responseObject);
        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());

        return $response;
    }

    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::AUTHORIZE;
    }
}
