<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\IntegrationBundle\Entity\Channel;
use Oro\Bundle\IntegrationBundle\Generator\Prefixed\PrefixedIntegrationIdentifierGenerator;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\StripePaymentBundle\ReAuthorization\ReAuthorizationExecutorInterface;
use Oro\Bundle\StripePaymentBundle\StripePaymentIntent\Action\StripePaymentIntentActionInterface;

class LoadNonApplicableReAuthorizationTransactionsData extends AbstractLoadReAuthorizationTransactionsData
{
    public const string TRANSACTION_WITHOUT_RE_AUTHORIZATION = 'payment_transaction_without_re_authorization';
    public const string TRANSACTION_WITHOUT_CUSTOMER_ID = 'payment_transaction_without_customer_id';
    public const string TRANSACTION_WITHOUT_METHOD_ID = 'payment_transaction_without_method_id';

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

        // Create non-applicable transactions.
        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_WITHOUT_RE_AUTHORIZATION,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 22), // 6d22h ago,
            transactionOptions: [
                StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_123',
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_123',
                StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123',
            ]
        );

        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_WITHOUT_CUSTOMER_ID,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 22), // 6d22h ago,
            transactionOptions: [
                ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true,
                StripePaymentIntentActionInterface::PAYMENT_METHOD_ID => 'pm_123',
                StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123',
            ]
        );

        $this->createPaymentTransaction(
            manager: $manager,
            reference: self::TRANSACTION_WITHOUT_METHOD_ID,
            paymentMethod: $stripePaymentElementMethodIdentifier,
            action: PaymentMethodInterface::AUTHORIZE,
            successful: true,
            active: true,
            createdAt: $this->getDateInRange(6, 22), // 6d22h ago,
            transactionOptions: [
                ReAuthorizationExecutorInterface::RE_AUTHORIZATION_ENABLED => true,
                StripePaymentIntentActionInterface::CUSTOMER_ID => 'cus_123',
                StripePaymentIntentActionInterface::PAYMENT_INTENT_ID => 'pi_123',
            ]
        );

        $manager->flush();
    }
}
