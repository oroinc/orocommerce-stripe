<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\EventListener;

use Oro\Bundle\EmailBundle\Model\Recipient;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Event\ReAuthorizationFailureEvent;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\Config\StripeReAuthorizationConfigProviderInterface;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailModel;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailSenderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;

/**
 * Sends an email when a payment re-authorization fails.
 */
final class SendReAuthorizationFailureEmailListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly StripeReAuthorizationConfigProviderInterface $stripeReAuthorizationConfigProvider,
        private readonly ReAuthorizationFailureEmailSenderInterface $reAuthorizationFailureEmailSender
    ) {
        $this->logger = new NullLogger();
    }

    public function onReAuthorizationFailure(ReAuthorizationFailureEvent $event): void
    {
        $paymentTransaction = $event->getPaymentTransaction();
        $paymentMethodIdentifier = $paymentTransaction->getPaymentMethod();
        $paymentConfig = $this->stripeReAuthorizationConfigProvider->getPaymentConfig($paymentMethodIdentifier);
        if (!$paymentConfig) {
            $this->logNoPaymentConfig($paymentTransaction);

            return;
        }

        $recipients = array_map(
            static fn (string $email) => new Recipient($email),
            $paymentConfig->getReAuthorizationEmail()
        );

        if (!$recipients) {
            return;
        }

        $this->reAuthorizationFailureEmailSender->sendEmail(
            new ReAuthorizationFailureEmailModel(
                paymentTransaction: $paymentTransaction,
                paymentMethodResult: $event->getPaymentMethodResult(),
                recipients: $recipients,
                emailTemplateName: $paymentConfig->getReAuthorizationEmailTemplate(),
            )
        );
    }

    private function logNoPaymentConfig(PaymentTransaction $paymentTransaction): void
    {
        $this->logger->warning(
            'Failed to handle the renewal failure of the payment authorization '
            . 'for the payment transaction #{paymentTransactionId}: '
            . 'payment method {paymentMethodIdentifier} is not found.',
            [
                'paymentTransactionId' => $paymentTransaction->getId(),
                'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
            ]
        );
    }
}
