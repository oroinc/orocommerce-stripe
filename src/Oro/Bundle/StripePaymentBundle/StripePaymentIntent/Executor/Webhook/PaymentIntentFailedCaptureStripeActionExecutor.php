<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\Webhook;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentWebhookActionInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Executor\StripePaymentIntentActionExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResult;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Result\StripePaymentIntentActionResultInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Stripe\PaymentIntent as StripePaymentIntent;

/**
 * Handles the "payment_intent.payment_failed" webhook event as a payment method action.
 *
 * @see https://docs.stripe.com/api/payment_intents
 * @see https://docs.stripe.com/api/events/object
 * @see https://docs.stripe.com/api/events/types#event_types-payment_intent.payment_failed
 */
class PaymentIntentFailedCaptureStripeActionExecutor implements
    StripePaymentIntentActionExecutorInterface,
    LoggerAwareInterface
{
    use LoggerAwareTrait;

    public const string EVENT_TYPE = 'payment_intent.payment_failed';
    public const string ACTION_NAME = 'webhook:' . self::EVENT_TYPE;

    public function __construct(
        private readonly PaymentTransactionProvider $paymentTransactionProvider
    ) {
        $this->logger = new NullLogger();
    }

    #[\Override]
    public function isSupportedByActionName(string $stripeActionName): bool
    {
        return $stripeActionName === self::ACTION_NAME;
    }

    #[\Override]
    public function isApplicableForAction(StripePaymentIntentActionInterface $stripeAction): bool
    {
        if (!$stripeAction instanceof StripePaymentIntentWebhookActionInterface) {
            return false;
        }

        if ($stripeAction->getStripeEvent()->type !== self::EVENT_TYPE) {
            return false;
        }

        return $stripeAction->getPaymentTransaction()->getAction() === PaymentMethodInterface::AUTHORIZE;
    }

    #[\Override]
    public function executeAction(
        StripePaymentIntentActionInterface $stripeAction
    ): StripePaymentIntentActionResultInterface {
        if (!$stripeAction instanceof StripePaymentIntentWebhookActionInterface) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Argument $stripeAction is expected to be an instance of %s, got %s',
                    StripePaymentIntentWebhookActionInterface::class,
                    get_debug_type($stripeAction)
                )
            );
        }

        $authorizeTransaction = $stripeAction->getPaymentTransaction();

        $stripeEvent = $stripeAction->getStripeEvent();
        /** @var StripePaymentIntent $stripePaymentIntent */
        $stripePaymentIntent = $stripeEvent->data->object;

        $captureTransaction = $this->findOrCreateCaptureTransaction($authorizeTransaction, $stripePaymentIntent);

        $captureTransaction->setActive(false);
        $captureTransaction->setSuccessful(false);
        $captureTransaction->setAmount($authorizeTransaction->getAmount());
        $captureTransaction->setCurrency($authorizeTransaction->getCurrency());
        $captureTransaction->setReference($stripePaymentIntent->id);
        $captureTransaction->addTransactionOption(
            StripePaymentIntentActionInterface::PAYMENT_INTENT_ID,
            $stripePaymentIntent->id
        );
        $captureTransaction->addWebhookRequestLog($stripeEvent->toArray());

        $this->paymentTransactionProvider->savePaymentTransaction($captureTransaction);

        $authorizeTransaction->setActive(false);

        return new StripePaymentIntentActionResult(successful: true, stripePaymentIntent: $stripePaymentIntent);
    }

    private function findOrCreateCaptureTransaction(
        PaymentTransaction $authorizeTransaction,
        StripePaymentIntent $stripePaymentIntent
    ): PaymentTransaction {
        return $this->paymentTransactionProvider->findOrCreateByPaymentTransaction(
            PaymentMethodInterface::CAPTURE,
            $authorizeTransaction,
            ['reference' => $stripePaymentIntent->id]
        );
    }
}
