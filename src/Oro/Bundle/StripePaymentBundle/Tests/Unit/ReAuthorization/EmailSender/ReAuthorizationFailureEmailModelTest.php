<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Unit\ReAuthorization\EmailSender;

use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\EmailSender\ReAuthorizationFailureEmailModel;
use PHPUnit\Framework\TestCase;

final class ReAuthorizationFailureEmailModelTest extends TestCase
{
    /**
     * @dataProvider constructorParametersDataProvider
     */
    public function testConstructorAndGetters(
        PaymentTransaction $paymentTransaction,
        array $paymentMethodResult,
        array $recipients,
        string $emailTemplateName
    ): void {
        $model = new ReAuthorizationFailureEmailModel(
            $paymentTransaction,
            $paymentMethodResult,
            $recipients,
            $emailTemplateName
        );

        self::assertSame($paymentTransaction, $model->getPaymentTransaction());
        self::assertSame($paymentMethodResult, $model->getPaymentMethodResult());
        self::assertSame($recipients, $model->getRecipients());
        self::assertSame($emailTemplateName, $model->getEmailTemplateName());
    }

    public function constructorParametersDataProvider(): array
    {
        $paymentTransaction = new PaymentTransaction();

        return [
            'typical values' => [
                'paymentTransaction' => $paymentTransaction,
                'paymentMethodResult' => ['successful' => false, 'error' => 'Declined'],
                'recipients' => ['admin@example.com'],
                'emailTemplateName' => 'stripe_payment_element_re_authorization_failure',
            ],
            'empty payment method result' => [
                'paymentTransaction' => $paymentTransaction,
                'paymentMethodResult' => [],
                'recipients' => ['admin@example.com'],
                'emailTemplateName' => 'stripe_payment_element_re_authorization_failure',
            ],
            'empty recipients' => [
                'paymentTransaction' => $paymentTransaction,
                'paymentMethodResult' => ['successful' => false, 'error' => 'Declined'],
                'recipients' => [],
                'emailTemplateName' => 'stripe_payment_element_re_authorization_failure',
            ],
            'empty template name' => [
                'paymentTransaction' => $paymentTransaction,
                'paymentMethodResult' => ['successful' => false],
                'recipients' => ['admin@example.com'],
                'emailTemplateName' => '',
            ],
            'multiple recipients' => [
                'paymentTransaction' => $paymentTransaction,
                'paymentMethodResult' => ['successful' => false, 'error' => 'Declined'],
                'recipients' => ['admin@example.com', 'finance@example.com', 'manager@example.com'],
                'emailTemplateName' => 'stripe_payment_element_re_authorization_failure',
            ],
            'complex payment method result' => [
                'paymentTransaction' => $paymentTransaction,
                'paymentMethodResult' => [
                    'successful' => false,
                    'error' => 'Declined',
                ],
                'recipients' => ['admin@example.com'],
                'emailTemplateName' => 'stripe_payment_element_re_authorization_failure_complex',
            ],
        ];
    }
}
