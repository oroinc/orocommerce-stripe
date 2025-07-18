<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Generator\Prefixed\PrefixedIntegrationIdentifierGenerator;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;

class LoadApplicableReAuthorizationTransactionsData extends AbstractLoadReAuthorizationTransactionsData
{
    public const string TRANSACTION_APPLICABLE_1 = 'payment_transaction_applicable_1';
    public const string TRANSACTION_APPLICABLE_2 = 'payment_transaction_applicable_2';

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

        // Create applicable transactions (created between 6d20h and 7d ago).
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_APPLICABLE_1,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 22), // 6d22h ago,
            transactionOptions: [
                ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true,
                StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_123',
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_123',
                StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123',
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
                StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_456',
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_456',
                StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123',
            ]
        );

        $manager->flush();
    }
}
