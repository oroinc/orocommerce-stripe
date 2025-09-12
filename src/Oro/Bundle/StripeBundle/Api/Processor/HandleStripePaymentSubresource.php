<?php

namespace Oro\Bundle\StripeBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Exception\RuntimeException;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Model\ErrorMetaProperty;
use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\CheckoutBundle\Api\Processor\AbstractHandlePaymentSubresource;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\PaymentStatus\PaymentStatuses;
use Oro\Bundle\StripeBundle\Api\Model\StripePaymentRequest;

/**
 * Handles the checkout Stripe payment sub-resource.
 */
class HandleStripePaymentSubresource extends AbstractHandlePaymentSubresource
{
    #[\Override]
    protected function getInProgressStatuses(): array
    {
        return [
            PaymentStatuses::PENDING,
            PaymentStatuses::DECLINED
        ];
    }

    #[\Override]
    protected function getErrorStatuses(): array
    {
        return [
            PaymentStatuses::CANCELED
        ];
    }

    #[\Override]
    protected function getPaymentTransactionOptions(
        Checkout $checkout,
        ChangeSubresourceContext $context
    ): array {
        $paymentAdditionalData = $this->getPaymentAdditionalData($checkout);
        if (empty($paymentAdditionalData['stripePaymentMethodId'])) {
            throw new RuntimeException('Stripe payment method id not provided');
        }

        // Additional data must contain stripePaymentMethodId to start payment process
        // URLs are used when some user action is required, like passing 3D Secure checks or card setup.
        /** @var StripePaymentRequest $request */
        $request = $context->getResult()[$context->getAssociationName()];

        return [
            'failureUrl' => $request->getFailureUrl(),
            'partiallyPaidUrl' => $request->getPartiallyPaidUrl(),
            'successUrl' => $request->getSuccessUrl(),
            'additionalData' => json_encode(
                ['stripePaymentMethodId' => $paymentAdditionalData['stripePaymentMethodId']],
                JSON_THROW_ON_ERROR
            )
        ];
    }

    #[\Override]
    protected function processPaymentError(
        Checkout $checkout,
        Order $order,
        array $paymentResult,
        ChangeSubresourceContext $context
    ): void {
        // handle additional user actions (3D secure validation, etc.. )
        if (!empty($paymentResult['requires_action'])) {
            $context->addError($this->createRequireAdditionalActionError($paymentResult));

            return;
        }

        $this->onPaymentError($checkout, $context);
        $this->saveChanges($context);
        $context->addError(Error::createValidationError(
            'payment constraint',
            $this->getPaymentErrorDetail($paymentResult)
        ));
    }

    private function createRequireAdditionalActionError(array $paymentResult): Error
    {
        $error = Error::createValidationError(
            'payment action constraint',
            'The payment requires additional actions.'
        );
        if ($paymentResult) {
            if (\array_key_exists('additionalData', $paymentResult)
                && \is_string($paymentResult['additionalData'])
            ) {
                $paymentResult['additionalData'] = json_decode(
                    $paymentResult['additionalData'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            }
            $error->addMetaProperty('data', new ErrorMetaProperty($paymentResult, 'array'));
        }

        return $error;
    }

    private function getPaymentErrorDetail(array $paymentResult): string
    {
        if (empty($paymentResult['error'])) {
            return 'Payment failed, please try again or select a different payment method.';
        }

        return \sprintf(
            '%s. Stripe Error Code: "%s", Decline code: "%s"',
            $paymentResult['error'],
            $paymentResult['stripe_error_code'] ?? '',
            $paymentResult['decline_code'] ?? ''
        );
    }
}
