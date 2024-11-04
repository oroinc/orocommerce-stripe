<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Client\Request\ConfirmRequest;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponse;
use Oro\Bundle\StripeBundle\Client\Response\StripeApiResponseInterface;
use Oro\Bundle\StripeBundle\Client\StripeGatewayFactoryInterface;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Provider\EntitiesTransactionsProvider;

/**
 * Prepare request data for Payment confirmation request.
 */
class ConfirmPaymentAction extends PaymentActionAbstract implements PaymentActionInterface
{
    private EntitiesTransactionsProvider $entitiesTransactionsProvider;

    public function __construct(
        StripeGatewayFactoryInterface $clientFactory,
        EntitiesTransactionsProvider $entitiesTransactionsProvider
    ) {
        parent::__construct($clientFactory);
        $this->entitiesTransactionsProvider = $entitiesTransactionsProvider;
    }

    #[\Override]
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

    #[\Override]
    public function isApplicable(string $action, PaymentTransaction $paymentTransaction): bool
    {
        return $action === self::CONFIRM_ACTION
            && !$this->entitiesTransactionsProvider->hasEntities($paymentTransaction);
    }
}
