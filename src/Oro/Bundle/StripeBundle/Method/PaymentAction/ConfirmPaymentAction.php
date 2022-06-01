<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\ConfirmRequest;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Prepare request data for Payment confirmation request.
 */
class ConfirmPaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $request = new ConfirmRequest($paymentTransaction);
        $responseObject = $this->getClient($config)->confirm($request);

        $response = new StripeApiResponse($responseObject);
        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());

        return $response;
    }

    public function isApplicable(string $action): bool
    {
        return $action === self::CONFIRM_ACTION;
    }
}
