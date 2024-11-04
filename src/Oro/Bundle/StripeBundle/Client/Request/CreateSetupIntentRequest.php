<?php

namespace Oro\Bundle\StripeBundle\Client\Request;

use Oro\Bundle\StripeBundle\Model\CustomerResponse;

/**
 * Prepare data for create SetupIntent request.
 */
class CreateSetupIntentRequest extends StripeApiRequestAbstract
{
    private const DEFAULT_USAGE_PARAM_VALUE = 'off_session';

    #[\Override]
    public function getRequestData(): array
    {
        $requestData = [
            'payment_method' => $this->getPaymentMethodId(),
            'confirm' => true,
            'metadata' => [
                'order_id' => $this->transaction->getEntityIdentifier()
            ]
        ];

        // If customer is present we suppose the payment should be stored for future usage.
        $additionalData = $this->getTransactionAdditionalData();
        $customer = $additionalData[CustomerResponse::CUSTOMER_ID_PARAM] ?? null;
        if ($customer) {
            $requestData = array_merge($requestData, [
                'customer' => $customer,
                'usage' => self::DEFAULT_USAGE_PARAM_VALUE
            ]);
        }

        return $requestData;
    }

    #[\Override]
    public function getPaymentId(): ?string
    {
        return null;
    }
}
