<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripeBundle\Client\Request\PurchaseRequest;
use Oro\Bundle\StripeBundle\Client\Response\PurchaseResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Method\StripePaymentActionMapper;
use Oro\Bundle\StripeBundle\Model\PaymentIntentResponse;
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
     */
    protected function updateTransactionData(
        PaymentTransaction $paymentTransaction,
        ResponseObjectInterface $responseObject,
        ?bool $successful = null
    ): void {
        $paymentTransaction->setReference($responseObject->getIdentifier());

        $responseData = array_merge(
            ['source' => ResponseObjectInterface::ACTION_SOURCE_API],
            $responseObject->getData()
        );
        $paymentTransaction->setResponse($responseData);

        if (null !== $successful) {
            $paymentTransaction->setSuccessful($successful);
        }
    }

    protected function getTransactionAdditionalData(PaymentTransaction $paymentTransaction): array
    {
        $transactionOptions = $paymentTransaction->getTransactionOptions();

        return isset($transactionOptions['additionalData'])
            ? json_decode($transactionOptions['additionalData'], true)
            : [];
    }

    protected function executePurchase(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ): StripeApiResponseInterface {
        $client = $this->getClient($config);

        $request = new PurchaseRequest($config, $paymentTransaction);
        $responseObject = $client->purchase($request);

        $action = $this->getAction($config->getPaymentAction());
        $paymentTransaction->setAction($action);

        $response = new PurchaseResponse($responseObject);
        $paymentTransaction->setActive($response->isSuccessful() && $action === PaymentMethodInterface::AUTHORIZE);
        $this->updateTransactionOptions(
            $paymentTransaction,
            [
                PaymentIntentResponse::PAYMENT_INTENT_ID_PARAM => $responseObject->getIdentifier()
            ]
        );
        $this->updateTransactionData($paymentTransaction, $responseObject, $response->isSuccessful());

        return $response;
    }

    protected function updateTransactionOptions(PaymentTransaction $paymentTransaction, array $data): void
    {
        $transactionOptions = $paymentTransaction->getTransactionOptions();
        $additionalOptions = (array)json_decode($transactionOptions['additionalData'] ?? null, true);
        $additionalOptions = array_merge($additionalOptions, $data);
        $transactionOptions['additionalData'] = json_encode($additionalOptions);
        $paymentTransaction->setTransactionOptions($transactionOptions);
    }

    protected function getAction(string $action): string
    {
        return StripePaymentActionMapper::getPaymentAction($action);
    }
}
