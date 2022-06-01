<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;
use Oro\Bundle\StripeBundle\Method\Config\StripePaymentConfig;

/**
 * Prepare data for purchase request.
 */
class PurchaseRequest extends StripeApiRequestAbstract
{
    private const DEFAULT_CONFIRMATION_METHOD = 'manual';
    private const STRIPE_PAYMENT_METHOD_ID_PARAM = 'stripePaymentMethodId';

    private StripePaymentConfig $config;

    public function __construct(StripePaymentConfig $config, PaymentTransaction $paymentTransaction)
    {
        parent::__construct($paymentTransaction);
        $this->config = $config;
    }

    public function getRequestData(): array
    {
        $additionalData = $this->getTransactionAdditionalData();

        if (empty($additionalData) || !isset($additionalData[self::STRIPE_PAYMENT_METHOD_ID_PARAM])) {
            throw new \LogicException(sprintf(
                'Payment request could not be perform: parameter "%s" is empty',
                'stripePaymentMethodId'
            ));
        }

        return [
            'payment_method' => $additionalData[self::STRIPE_PAYMENT_METHOD_ID_PARAM],
            'amount' => PaymentAmountConverter::convertToStripeFormat((float)$this->transaction->getAmount()),
            'currency' => $this->transaction->getCurrency(),
            'confirmation_method' => self::DEFAULT_CONFIRMATION_METHOD,
            'capture_method' => $this->config->getPaymentAction(),
            'confirm' => 'true',
            'metadata' => [
                'order_id' => $this->transaction->getEntityIdentifier()
            ]
        ];
    }

    public function getPaymentId(): ?string
    {
        return null;
    }
}
