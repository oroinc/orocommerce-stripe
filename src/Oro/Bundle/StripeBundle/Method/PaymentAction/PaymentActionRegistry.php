<?php

namespace Oro\Bundle\StripeBundle\Method\PaymentAction;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;

/**
 * Register all possible payment actions used in Application
 */
class PaymentActionRegistry
{
    private iterable $paymentActions;

    /**
     * @param iterable|PaymentActionInterface[] $paymentActions
     */
    public function __construct(iterable $paymentActions)
    {
        $this->paymentActions = $paymentActions;
    }

    public function getPaymentAction(string $type, PaymentTransaction $transaction): PaymentActionInterface
    {
        foreach ($this->paymentActions as $paymentAction) {
            if ($paymentAction->isApplicable($type, $transaction)) {
                return $paymentAction;
            }
        }

        throw new \LogicException(sprintf('Payment action %s is not supported', $type));
    }
}
