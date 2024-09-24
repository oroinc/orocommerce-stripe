<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Request\CaptureRequest;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Handle payment capturing.
 */
class CapturePaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    private PaymentTransactionProvider $paymentTransactionProvider;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        PaymentTransactionProvider $paymentTransactionProvider
    ) {
        parent::__construct($clientFactory);
        $this->paymentTransactionProvider = $paymentTransactionProvider;
    }

    #[\Override]
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $authorizeTransaction = $paymentTransaction->getSourcePaymentTransaction();
        if (!$authorizeTransaction) {
            throw new \LogicException('Payment could not be captured. Authorize transaction not found');
        }

        $paymentTransaction->setActive(true);
        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        try {
            $request = new CaptureRequest($authorizeTransaction);
            $responseObject = $this->getClient($config)->capture($request);
            $response = new StripeApiResponse($responseObject);
        } finally {
            $paymentTransaction->setActive(false);
        }

        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());
        $authorizeTransaction->setActive(!$paymentTransaction->isSuccessful());

        return $response;
    }

    #[\Override]
    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::CAPTURE;
    }
}
