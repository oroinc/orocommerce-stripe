<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\ResponseObjectInterface;

/**
 * Provides basic common methods to perform payment actions.
 */
abstract class PaymentActionAbstract
{
    protected StripeGatewayFactoryInterface $clientFactory;
    protected ?StripeGatewayInterface $client = null;

    public function __construct(StripeGatewayFactoryInterface $clientFactory)
    {
        $this->clientFactory = $clientFactory;
    }

    public function getClient(StripePaymentConfig $config): StripeGatewayInterface
    {
        if (null === $this->client) {
            $this->client = $this->clientFactory->create($config);
        }
        return $this->client;
    }

    /**
     * Update transaction data with response from API.
     *
     * @param PaymentTransaction $paymentTransaction
     * @param ResponseObjectInterface $responseObject
     * @param bool $successful
     */
    protected function updateTransactionData(
        PaymentTransaction $paymentTransaction,
        ResponseObjectInterface $responseObject,
        bool $successful
    ): void {
        $paymentTransaction->setReference($responseObject->getIdentifier());

        $responseData = array_merge(
            ['source' => ResponseObjectInterface::ACTION_SOURCE_API],
            $responseObject->getData()
        );
        $paymentTransaction->setResponse($responseData);
        $paymentTransaction->setSuccessful($successful);
    }
}
