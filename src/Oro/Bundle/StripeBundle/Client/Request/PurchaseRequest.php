<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;
use Oro\Bundle\StripeBundle\Model\CustomerResponse;
use Oro\Bundle\StripeBundle\Model\SetupIntentResponse;

/**
 * Prepare data for purchase request.
 */
class PurchaseRequest extends StripeApiRequestAbstract
{
    private const DEFAULT_CONFIRMATION_METHOD = 'manual';

    private StripePaymentConfig $config;

    public function __construct(
        StripePaymentConfig $config,
        PaymentTransaction $paymentTransaction
    ) {
        parent::__construct($paymentTransaction);
        $this->config = $config;
    }

    public function getRequestData(): array
    {
        $additionalOptions = $this->getTransactionAdditionalData();
        $isOffSession = !empty($additionalOptions[SetupIntentResponse::SETUP_INTENT_ID_PARAM]);

        $requestData = [
            'payment_method' => $this->getPaymentMethodId(),
            'amount' => PaymentAmountConverter::convertToStripeFormat((float)$this->transaction->getAmount()),
            'currency' => $this->transaction->getCurrency(),
            'confirmation_method' => self::DEFAULT_CONFIRMATION_METHOD,
            'capture_method' => $this->config->getPaymentAction(),
            'confirm' => true,
            'off_session' => $isOffSession,
            'metadata' => [
                'order_id' => $this->transaction->getEntityIdentifier()
            ]
        ];

        if (isset($additionalOptions[CustomerResponse::CUSTOMER_ID_PARAM])) {
            $requestData['customer'] = $additionalOptions[CustomerResponse::CUSTOMER_ID_PARAM];
        }

        return $requestData;
    }

    public function getPaymentId(): ?string
    {
        return null;
    }
}
