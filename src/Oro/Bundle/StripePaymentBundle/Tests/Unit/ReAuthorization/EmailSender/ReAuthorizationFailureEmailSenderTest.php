<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\ReAuthorization\EmailSender;

use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\CurrencyBundle\Entity\Price;
use Oro\Bundle\EmailBundle\Entity\EmailUser;
use Oro\Bundle\EmailBundle\Model\From;
use Oro\Bundle\EmailBundle\Sender\EmailTemplateSender;
use Oro\Bundle\NotificationBundle\Model\NotificationSettings;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailModel;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailSender;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReAuthorizationFailureEmailSenderTest extends TestCase
{
    private MockObject&NotificationSettings $notificationSettings;

    private MockObject&EmailTemplateSender $emailTemplateSender;

    private MockObject&ManagerRegistry $doctrine;

    private ReAuthorizationFailureEmailSender $sender;

    protected function setUp(): void
    {
        $this->notificationSettings = $this->createMock(NotificationSettings::class);
        $this->emailTemplateSender = $this->createMock(EmailTemplateSender::class);
        $this->doctrine = $this->createMock(ManagerRegistry::class);

        $this->sender = new ReAuthorizationFailureEmailSender(
            $this->notificationSettings,
            $this->emailTemplateSender,
            $this->doctrine
        );

        $this->notificationSettings
            ->method('getSender')
            ->willReturn(From::emailAddress('sender@example.com'));
    }

    public function testSendEmailWithValidData(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAmount(123.45);
        $paymentTransaction->setCurrency('USD');
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setEntityIdentifier(42);
        $paymentTransaction->setCreatedAt(new \DateTime());

        $paymentMethodResult = ['successful' => false, 'error' => 'Authorization failed'];

        $reAuthorizationFailureEmailModel = new ReAuthorizationFailureEmailModel(
            $paymentTransaction,
            $paymentMethodResult,
            ['test@example.com'],
            'stripe_payment_element_re_authorization_failure'
        );

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $subjectEntity = new Order();
        $paymentTransactionRepository
            ->expects(self::once())
            ->method('find')
            ->with($paymentTransaction->getEntityIdentifier())
            ->willReturn($subjectEntity);

        $this->doctrine
            ->expects(self::once())
            ->method('getRepository')
            ->with($paymentTransaction->getEntityClass())
            ->willReturn($paymentTransactionRepository);

        $expectedEmailUser = new EmailUser();
        $this->emailTemplateSender
            ->expects(self::once())
            ->method('sendEmailTemplate')
            ->with(
                $this->notificationSettings->getSender(),
                $reAuthorizationFailureEmailModel->getRecipients(),
                $reAuthorizationFailureEmailModel->getEmailTemplateName(),
                [
                    'entity' => $paymentTransaction,
                    'subjectEntity' => $subjectEntity,
                    'subjectAmount' => Price::create(
                        $paymentTransaction->getAmount(),
                        $paymentTransaction->getCurrency()
                    ),
                    'errorMessage' => $paymentMethodResult['error'],
                    'errorDateTime' => $paymentTransaction->getCreatedAt(),
                ]
            )
            ->willReturn($expectedEmailUser);

        $result = $this->sender->sendEmail($reAuthorizationFailureEmailModel);

        self::assertSame($expectedEmailUser, $result);
    }

    public function testSendEmailWithEmptyError(): void
    {
        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setAmount(123.45);
        $paymentTransaction->setCurrency('USD');
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setEntityIdentifier(42);
        $paymentTransaction->setCreatedAt(new \DateTime());

        $paymentMethodResult = ['successful' => false];

        $reAuthorizationFailureEmailModel = new ReAuthorizationFailureEmailModel(
            $paymentTransaction,
            $paymentMethodResult,
            ['test@example.com'],
            'stripe_payment_element_re_authorization_failure'
        );

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $subjectEntity = new Order();
        $paymentTransactionRepository
            ->expects(self::once())
            ->method('find')
            ->with($paymentTransaction->getEntityIdentifier())
            ->willReturn($subjectEntity);

        $this->doctrine
            ->expects(self::once())
            ->method('getRepository')
            ->with($paymentTransaction->getEntityClass())
            ->willReturn($paymentTransactionRepository);

        $expectedEmailUser = new EmailUser();
        $this->emailTemplateSender
            ->expects(self::once())
            ->method('sendEmailTemplate')
            ->with(
                $this->notificationSettings->getSender(),
                $reAuthorizationFailureEmailModel->getRecipients(),
                $reAuthorizationFailureEmailModel->getEmailTemplateName(),
                [
                    'entity' => $paymentTransaction,
                    'subjectEntity' => $subjectEntity,
                    'subjectAmount' => Price::create(
                        $paymentTransaction->getAmount(),
                        $paymentTransaction->getCurrency()
                    ),
                    'errorMessage' => '',
                    'errorDateTime' => $paymentTransaction->getCreatedAt(),
                ]
            )
            ->willReturn($expectedEmailUser);

        $result = $this->sender->sendEmail($reAuthorizationFailureEmailModel);

        self::assertSame($expectedEmailUser, $result);
    }
}
