<?php

namespace Oro\Bundle\StripeBundle\Tests\Unit\Notification;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\LocaleBundle\Formatter\DateTimeFormatterInterface;
use Oro\Bundle\LocaleBundle\Formatter\NumberFormatter;
use Oro\Bundle\LocaleBundle\Model\LocaleSettings;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripeBundle\Notification\ReAuthorizeMessageNotifications;
use Oro\Bundle\StripeBundle\Notification\StripeNotificationManager;
use Oro\Component\Testing\Unit\EntityTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReAuthorizeMessageNotificationsTest extends TestCase
{
    use EntityTrait;

    private StripeNotificationManager|MockObject $notificationManager;
    private DateTimeFormatterInterface|MockObject $dateTimeFormatter;
    private NumberFormatter|MockObject $numberFormatter;
    private TranslatorInterface|MockObject $translator;
    private DoctrineHelper|MockObject $doctrineHelper;
    private LocaleSettings|MockObject $localeSettings;
    private ReAuthorizeMessageNotifications $messageNotifications;

    #[\Override]
    protected function setUp(): void
    {
        $this->notificationManager = $this->createMock(StripeNotificationManager::class);
        $this->dateTimeFormatter = $this->createMock(DateTimeFormatterInterface::class);
        $this->numberFormatter = $this->createMock(NumberFormatter::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->doctrineHelper = $this->createMock(DoctrineHelper::class);
        $this->localeSettings = $this->createMock(LocaleSettings::class);

        $this->messageNotifications = new ReAuthorizeMessageNotifications(
            $this->notificationManager,
            $this->dateTimeFormatter,
            $this->numberFormatter,
            $this->translator,
            $this->doctrineHelper,
            $this->localeSettings,
        );
    }

    public function testSendAuthorizationFailed()
    {
        $paymentTransaction = $this->getEntity(PaymentTransaction::class, [
            'action' => 'authorize',
            'amount' => '20.00',
            'currency' => 'USD',
            'entityClass' => Order::class,
            'entityIdentifier' => 1,
            'active' => true,
            'successful' => true
        ]);

        $formattedDate = (new \DateTime('now'))->format('Y-m-d');
        $formattedTime = (new \DateTime('now'))->format('H:i:s');

        $this->localeSettings->expects($this->once())
            ->method('getTimeZone')
            ->willReturn('America/Los_Angeles');

        $this->numberFormatter->expects($this->once())
            ->method('formatCurrency')
            ->with('20.00', 'USD')
            ->willReturn('$20.00');

        $this->dateTimeFormatter->expects($this->once())
            ->method('formatDate')
            ->willReturn($formattedDate);

        $this->dateTimeFormatter->expects($this->once())
            ->method('formatTime')
            ->willReturn($formattedTime);

        $this->translator->expects($this->exactly(2))
            ->method('trans')
            ->withConsecutive(
                [
                    'oro.stripe.re-authorize.error.authorization-failed.message',
                    [
                        '%amount%' => '$20.00',
                        '%order%' => '#1-3',
                        '%date%' => $formattedDate,
                        '%time%' => $formattedTime,
                        '%reason%' => 'Payment card declined'
                    ],
                ],
                [
                    'oro.stripe.re-authorize.error.authorization-failed.subject',
                    ['%order%' => '#1-3']
                ]
            )
            ->willReturnOnConsecutiveCalls(
                'Email content',
                'Email subject'
            );

        $entity = $this->getEntity(Order::class, [
            'identifier' => '1-3'
        ]);
        $this->doctrineHelper->expects($this->once())
            ->method('getEntity')
            ->willReturn($entity);

        $this->notificationManager->expects($this->once())
            ->method('sendNotification');

        $this->doctrineHelper->expects($this->never())
            ->method('getSingleEntityIdentifier');

        $this->messageNotifications->sendAuthorizationFailed(
            $paymentTransaction,
            ['test@test.com'],
            'Payment card declined'
        );
    }

    public function testSendAuthorizationFailedWhenEntityIsNotOrder()
    {
        $paymentTransaction = $this->getEntity(PaymentTransaction::class, [
            'action' => 'authorize',
            'amount' => '20.00',
            'currency' => 'USD',
            'entityClass' => Order::class,
            'entityIdentifier' => 1,
            'active' => true,
            'successful' => true
        ]);

        $formattedDate = (new \DateTime('now'))->format('Y-m-d');
        $formattedTime = (new \DateTime('now'))->format('H:i:s');

        $this->localeSettings->expects($this->once())
            ->method('getTimeZone')
            ->willReturn('America/Los_Angeles');

        $this->numberFormatter->expects($this->once())
            ->method('formatCurrency')
            ->with('20.00', 'USD')
            ->willReturn('$20.00');

        $this->dateTimeFormatter->expects($this->once())
            ->method('formatDate')
            ->willReturn($formattedDate);

        $this->dateTimeFormatter->expects($this->once())
            ->method('formatTime')
            ->willReturn($formattedTime);

        $this->translator->expects($this->exactly(2))
            ->method('trans')
            ->withConsecutive(
                [
                    'oro.stripe.re-authorize.error.authorization-failed.message',
                    [
                        '%amount%' => '$20.00',
                        '%order%' => '#1',
                        '%date%' => $formattedDate,
                        '%time%' => $formattedTime,
                        '%reason%' => 'Payment card declined'
                    ],
                ],
                [
                    'oro.stripe.re-authorize.error.authorization-failed.subject',
                    ['%order%' => '#1']
                ]
            )
            ->willReturnOnConsecutiveCalls(
                'Email content',
                'Email subject'
            );

        $this->doctrineHelper->expects($this->once())
            ->method('getEntity')
            ->willReturn(new \stdClass());

        $this->notificationManager->expects($this->once())
            ->method('sendNotification');

        $this->doctrineHelper->expects($this->once())
            ->method('getSingleEntityIdentifier')
            ->willReturn(1);

        $this->messageNotifications->sendAuthorizationFailed(
            $paymentTransaction,
            ['test@test.com'],
            'Payment card declined'
        );
    }
}
