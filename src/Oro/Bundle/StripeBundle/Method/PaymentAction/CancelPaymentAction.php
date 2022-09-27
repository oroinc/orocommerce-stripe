<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripeBundle\Client\Request\CancelRequest;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Implement logic to handle transaction cancel process.
 */
class CancelPaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    private PaymentTransactionProvider $paymentTransactionProvider;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        PaymentTransactionProvider $paymentTransactionProvider
    ) {
        parent::__construct($clientFactory);
        $this->paymentTransactionProvider = $paymentTransactionProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $sourceTransaction = $paymentTransaction->getSourcePaymentTransaction();
        if (!$sourceTransaction) {
            throw new \LogicException('Payment could not be canceled. Authorize transaction not found');
        }

        if ($sourceTransaction->getAction() !== PaymentMethodInterface::AUTHORIZE) {
            throw new \LogicException('Payment could not be canceled. Transaction should be authorized first');
        }

        $paymentTransaction->setActive(true);
        $this->paymentTransactionProvider->savePaymentTransaction($paymentTransaction);

        $request = new CancelRequest($paymentTransaction);
        $responseObject = $this->getClient($config)->cancel($request);

        $response = new StripeApiResponse($responseObject);
        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());
        $sourceTransaction->setActive(false);

        return $response;
    }

    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === PaymentMethodInterface::CANCEL;
    }
}
