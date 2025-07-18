<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\EventListener;

use Oro\Bundle\EmailBundle\Model\Recipient;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\Event\ReAuthorizationFailureEvent;
use Oro\Bundle\StripePaymentBundle\EventListener\SendReAuthorizationFailureEmailListener;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfig;
use Oro\Bundle\StripePaymentBundle\PaymentMethod\StripePaymentElement\Config\StripePaymentElementConfigProvider;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailModel;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailSenderInterface;
use Oro\Bundle\TestFrameworkBundle\Test\Logger\LoggerAwareTraitTestTrait;
use Oro\Component\Testing\ReflectionUtil;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SendReAuthorizationFailureEmailListenerTest extends TestCase
{
    use LoggerAwareTraitTestTrait;

    private SendReAuthorizationFailureEmailListener $listener;

    private MockObject&StripePaymentElementConfigProvider $stripePaymentElementConfigProvider;

    private MockObject&ReAuthorizationFailureEmailSenderInterface $reAuthorizationFailureEmailSender;

    protected function setUp(): void
    {
        $this->stripePaymentElementConfigProvider = $this->createMock(StripePaymentElementConfigProvider::class);
        $this->reAuthorizationFailureEmailSender = $this->createMock(ReAuthorizationFailureEmailSenderInterface::class);

        $this->listener = new SendReAuthorizationFailureEmailListener(
            $this->stripePaymentElementConfigProvider,
            $this->reAuthorizationFailureEmailSender
        );
        $this->setUpLoggerMock($this->listener);
    }

    public function testOnReAuthorizationFailureSendsEmail(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 101);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $reAuthorizationFailureEvent = new ReAuthorizationFailureEvent(
            $paymentTransaction,
            ['successful' => false, 'error' => 'Declined']
        );

        $email1 = 'email@example.org';
        $email2 = 'john@example.com';
        $stripePaymentElementConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL => [$email1, $email2],
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL_TEMPLATE =>
                'stripe_payment_element_re_authorization_failure',
        ]);

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfig')
            ->with($paymentTransaction->getPaymentMethod())
            ->willReturn($stripePaymentElementConfig);

        $this->assertLoggerNotCalled();

        $this->reAuthorizationFailureEmailSender
            ->expects(self::once())
            ->method('sendEmail')
            ->with(
                new ReAuthorizationFailureEmailModel(
                    paymentTransaction: $paymentTransaction,
                    paymentMethodResult: $reAuthorizationFailureEvent->getPaymentMethodResult(),
                    recipients: [new Recipient($email1), new Recipient($email2)],
                    emailTemplateName: $stripePaymentElementConfig->getReAuthorizationEmailTemplate(),
                )
            );

        $this->listener->onReAuthorizationFailure($reAuthorizationFailureEvent);
    }

    public function testOnReAuthorizationFailureDoesNothingWhenNoPaymentConfig(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 101);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $reAuthorizationFailureEvent = new ReAuthorizationFailureEvent(
            $paymentTransaction,
            ['successful' => false, 'error' => 'Declined']
        );

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfig')
            ->with($paymentTransaction->getPaymentMethod())
            ->willReturn(null);

        $this->loggerMock
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to handle the renewal failure of the payment authorization '
                . 'for the payment transaction #{paymentTransactionId}: '
                . 'payment method {paymentMethodIdentifier} is not found.',
                [
                    'paymentTransactionId' => $paymentTransaction->getId(),
                    'paymentMethodIdentifier' => $paymentTransaction->getPaymentMethod(),
                ]
            );

        $this->reAuthorizationFailureEmailSender
            ->expects(self::never())
            ->method('sendEmail');

        $this->listener->onReAuthorizationFailure($reAuthorizationFailureEvent);
    }

    public function testOnReAuthorizationFailureDoesNothingWhenNoRecipients(): void
    {
        $paymentTransaction = new PaymentTransaction();
        ReflectionUtil::setId($paymentTransaction, 101);
        $paymentTransaction->setPaymentMethod('stripe_payment_element_42');

        $reAuthorizationFailureEvent = new ReAuthorizationFailureEvent(
            $paymentTransaction,
            ['successful' => false, 'error' => 'Declined']
        );

        $stripePaymentElementConfig = new StripePaymentElementConfig([
            StripePaymentElementConfig::RE_AUTHORIZATION_EMAIL => [],
        ]);

        $this->stripePaymentElementConfigProvider
            ->expects(self::once())
            ->method('getPaymentConfig')
            ->with($paymentTransaction->getPaymentMethod())
            ->willReturn($stripePaymentElementConfig);

        $this->assertLoggerNotCalled();

        $this->reAuthorizationFailureEmailSender
            ->expects(self::never())
            ->method('sendEmail');

        $this->listener->onReAuthorizationFailure($reAuthorizationFailureEvent);
    }
}
