<?php

namespace Oro\Bundle\StripePaymentBundle\Api\Processor;

use Oro\Bundle\ApiBundle\Exception\RuntimeException;
use Oro\Bundle\ApiBundle\Model\Error;
use Oro\Bundle\ApiBundle\Model\ErrorMetaProperty;
use Oro\Bundle\ApiBundle\Processor\Subresource\ChangeSubresourceContext;
use Oro\Bundle\CheckoutBundle\Api\Processor\AbstractHandlePaymentSubresource;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\PaymentStatus\PaymentStatuses;
use Oro\Bundle\StripePaymentBundle\Api\Model\StripePaymentElementPaymentRequest;

/**
 * Handles the Stripe Payment Element checkout payment sub-resource.
 */
class HandleStripePaymentElementPaymentSubresource extends AbstractHandlePaymentSubresource
{
    #[\Override]
    protected function getInProgressStatuses(): array
    {
        return [
            PaymentStatuses::PENDING,
            PaymentStatuses::DECLINED,
        ];
    }

    #[\Override]
    protected function getErrorStatuses(): array
    {
        return [
            PaymentStatuses::CANCELED,
        ];
    }

    #[\Override]
    protected function getPaymentTransactionOptions(
        Checkout $checkout,
        ChangeSubresourceContext $context
    ): array {
        $paymentAdditionalData = $this->getPaymentAdditionalData($checkout);
        if (empty($paymentAdditionalData['confirmationToken'])) {
            throw new RuntimeException('Stripe confirmation token not provided');
        }

        // Additional data must contain confirmationToken to start payment process
        // URLs are used when some user action is required, like passing 3D Secure checks or card setup.
        /** @var StripePaymentElementPaymentRequest $request */
        $request = $context->getResult()[$context->getAssociationName()];

        return [
            'failureUrl' => $request->getFailureUrl(),
            'partiallyPaidUrl' => $request->getPartiallyPaidUrl(),
            'successUrl' => $request->getSuccessUrl(),
            'additionalData' => \json_encode(
                [
                    'confirmationToken' => [
                        'id' => $paymentAdditionalData['confirmationToken']['id'],
                        'paymentMethodPreview' => [
                            'type' => $paymentAdditionalData['confirmationToken']['paymentMethodPreview']['type']
                        ],
                    ],
                ],
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
        // Handles additional user actions (3D secure validation, etc.. ).
        if (!empty($paymentResult['requiresAction'])) {
            $context->addError($this->createRequireAdditionalActionError($paymentResult));

            return;
        }

        $this->onPaymentError($checkout, $context);
        $this->saveChanges($context);

        $context->addError(
            Error::createValidationError(
                'payment constraint',
                $this->getPaymentErrorDetail($paymentResult)
            )
        );
    }

    private function createRequireAdditionalActionError(array $paymentResult): Error
    {
        $error = Error::createValidationError(
            'payment action constraint',
            'The payment requires additional actions.'
        );

        if ($paymentResult) {
            if (\array_key_exists('additionalData', $paymentResult) && \is_string($paymentResult['additionalData'])) {
                $paymentResult['additionalData'] = \json_decode(
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

        $message = trim($paymentResult['error']);
        $details = [];

        if (!empty($paymentResult['errorCode'])) {
            $details[] = \sprintf('Error Code: "%s"', $paymentResult['errorCode']);
        }

        if (!empty($paymentResult['declineCode'])) {
            $details[] = \sprintf('Decline Code: "%s"', $paymentResult['declineCode']);
        }

        if ($details) {
            // Ensure the base message ends with a period before appending details.
            if (!str_ends_with($message, '.')) {
                $message .= '.';
            }

            return $message . ' ' . \implode(', ', $details);
        }

        return $message;
    }
}
