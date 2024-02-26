<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Converter\PaymentAmountConverter;

/**
 * Prepare request data to perform capture request to Stripe API.
 */
class CaptureRequest extends StripeApiRequestAbstract
{
    private ?float $amount;

    public function __construct(PaymentTransaction $transaction, float $amount = null)
    {
        parent::__construct($transaction);
        $this->amount = $amount;
    }

    public function getRequestData(): array
    {
        $amount = $this->amount ?? (float)$this->transaction->getAmount();
        $currency = $this->transaction->getCurrency();

        return ['amount_to_capture' => PaymentAmountConverter::convertToStripeFormatUsingCurrency($amount, $currency)];
    }
}
