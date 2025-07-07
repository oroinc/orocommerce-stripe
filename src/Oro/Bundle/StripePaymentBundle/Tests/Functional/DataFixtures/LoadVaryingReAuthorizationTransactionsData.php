<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Generator\Prefixed\PrefixedIntegrationIdentifierGenerator;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;

class LoadVaryingReAuthorizationTransactionsData extends AbstractLoadReAuthorizationTransactionsData
{
    public const string SAMPLE_PAYMENT_METHOD = 'sample_payment_method_42';

    public const string TRANSACTION_APPLICABLE_1 = 'payment_transaction_applicable_1';
    public const string TRANSACTION_APPLICABLE_2 = 'payment_transaction_applicable_2';
    public const string TRANSACTION_TOO_NEW = 'payment_transaction_too_new';
    public const string TRANSACTION_ONE_HOUR = 'payment_transaction_one_hour';
    public const string TRANSACTION_TOO_OLD = 'payment_transaction_too_old';
    public const string TRANSACTION_NOT_SUCCESSFUL = 'payment_transaction_not_successful';
    public const string TRANSACTION_NOT_ACTIVE = 'payment_transaction_not_active';
    public const string TRANSACTION_CAPTURE = 'payment_transaction_capture';
    public const string TRANSACTION_OTHER_METHOD = 'other_method';

    /**
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    #[\Override]
    public function load(ObjectManager $manager): void
    {
        /** @var Channel $integrationChanel */
        $integrationChanel = $this
            ->getReference(LoadStripePaymentElementChannelData::STRIPE_PAYMENT_ELEMENT_CHANNEL_1);
        /** @var PrefixedIntegrationIdentifierGenerator $prefixedIntegrationIdentifierGenerator */
        $prefixedIntegrationIdentifierGenerator = $this->container
            ->get('oro_stripe_payment.payment_method.stripe_payment_element.prefixed_identifier_generator');
        $stripePaymentElementMethodIdentifier = $prefixedIntegrationIdentifierGenerator
            ->generateIdentifier($integrationChanel);

        // Create eligible transactions (created between 6d20h and 7d ago)
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_APPLICABLE_1,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 21), // 6d21h ago,
            transactionOptions: [
                ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true,
                StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_123',
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_123',
            ]
        );

        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_APPLICABLE_2,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 22), // 6d22h ago
            transactionOptions: [
                ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true,
                StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_123',
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_123',
            ]
        );

        // Create transaction that's too new (created less than 6d20h ago)
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_TOO_NEW,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 19) // 6d19h ago
        );

        // Create transaction that's too new (created less than 6d20h ago)
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_ONE_HOUR,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(0, 1) // 1h ago
        );

        // Create transaction that's too old (created more than 7d ago)
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_TOO_OLD,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(7, 1) // 7d1h ago
        );

        // Create transaction that's not successful
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_NOT_SUCCESSFUL,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: false,
            active: true,
            createdAt: $this->getDateInRange(6, 21)
        );

        // Create transaction that's not active
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_NOT_ACTIVE,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: false,
            createdAt: $this->getDateInRange(6, 21)
        );

        // Create capture transaction (should not be included)
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_CAPTURE,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::CAPTURE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 21)
        );

        // Create transaction with other payment method (should not be included)
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_OTHER_METHOD,
            paymentMethod: self::SAMPLE_PAYMENT_METHOD,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 21)
        );

        $manager->flush();
    }
}
