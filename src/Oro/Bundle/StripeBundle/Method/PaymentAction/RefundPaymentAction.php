<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Request\RefundRequest;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Provides logic to refund captured transactions.
 */
class RefundPaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    private PaymentTransactionProvider $paymentTransactionProvider;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        PaymentTransactionProvider $paymentTransactionProvider
    ) {
        parent::__construct($clientFactory);
        $this->paymentTransactionProvider = $paymentTransactionProvider;
    }

    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $sourceTransaction = $paymentTransaction->getSourcePaymentTransaction();
        if (!$sourceTransaction) {
            throw new \LogicException('Payment could not be refunded. Capture transaction not found');
        }

        if ($sourceTransaction->getAction() !== PaymentMethodInterface::CAPTURE) {
            throw new \LogicException('Payment could not be refunded. Transaction should be captured first');
        }

        $paymentTransaction->setActive(true);
        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        $request = new RefundRequest($paymentTransaction);
        $responseObject = $this->getClient($config)->refund($request);

        $response = new StripeApiResponse($responseObject);
        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());
        $paymentTransaction->setActive(false);

        return $response;
    }

    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::REFUND;
    }
}
