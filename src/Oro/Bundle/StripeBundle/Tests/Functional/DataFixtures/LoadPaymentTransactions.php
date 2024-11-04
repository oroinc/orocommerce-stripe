<?php

namespace Oro\Bundle\StripeBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LoadPaymentTransactions extends AbstractFixture implements ContainerAwareInterface
{
    const STRIPE_PAYMENT_METHOD = 'stripe_1';
    const TEST_PAYMENT_METHOD = 'test_payment_2';

    const EXPIRED_AUTHORIZATION_TRANSACTION_1 = 'expired_authorization_transaction_1';
    const EXPIRED_AUTHORIZATION_TRANSACTION_2 = 'expired_authorization_transaction_2';
    const EXPIRED_AUTHORIZATION_FAILED_TRANSACTION = 'expired_authorization_failed_transaction';
    const EXPIRED_AUTHORIZATION_NOT_ACTIVE_TRANSACTION = 'expired_authorization_not_active_transaction';
    const ACTUAL_AUTHORIZATION_TRANSACTION = 'actual_authorization_transaction';
    const CAPTURE_TRANSACTION = 'capture_transaction';

    private ?ContainerInterface $container;
    private static array $paymentTransactionsData = [
        self::EXPIRED_AUTHORIZATION_TRANSACTION_1 => [
            'amount' => '10.00',
            'currency' => 'USD',
            'action' => PaymentMethodInterface::AUTHORIZE,
            'entityIdentifier' => 1,
            'paymentMethod' => self::TEST_PAYMENT_METHOD,
            'entityClass' => Order::class,
            'active' => true,
            'successful' => true
        ],
        self::EXPIRED_AUTHORIZATION_TRANSACTION_2 => [
            'amount' => '50.00',
            'currency' => 'USD',
            'action' => PaymentMethodInterface::AUTHORIZE,
            'entityIdentifier' => 2,
            'paymentMethod' => self::STRIPE_PAYMENT_METHOD,
            'entityClass' => Order::class,
            'active' => true,
            'successful' => true
        ],
        self::EXPIRED_AUTHORIZATION_FAILED_TRANSACTION => [
            'amount' => '20.00',
            'currency' => 'USD',
            'action' => PaymentMethodInterface::AUTHORIZE,
            'entityIdentifier' => 3,
            'paymentMethod' => self::STRIPE_PAYMENT_METHOD,
            'entityClass' => Order::class,
            'active' => true,
            'successful' => false
        ],
        self::EXPIRED_AUTHORIZATION_NOT_ACTIVE_TRANSACTION => [
            'amount' => '30.00',
            'currency' => 'USD',
            'action' => PaymentMethodInterface::AUTHORIZE,
            'entityIdentifier' => 4,
            'paymentMethod' => self::STRIPE_PAYMENT_METHOD,
            'entityClass' => Order::class,
            'active' => false,
            'successful' => true
        ],
        self::ACTUAL_AUTHORIZATION_TRANSACTION => [
            'amount' => '50.00',
            'currency' => 'USD',
            'action' => PaymentMethodInterface::AUTHORIZE,
            'entityIdentifier' => 5,
            'paymentMethod' => self::STRIPE_PAYMENT_METHOD,
            'entityClass' => Order::class,
            'active' => true,
            'successful' => true
        ],
        self::CAPTURE_TRANSACTION => [
            'amount' => '70.00',
            'currency' => 'USD',
            'action' => PaymentMethodInterface::CAPTURE,
            'entityIdentifier' => 7,
            'paymentMethod' => self::STRIPE_PAYMENT_METHOD,
            'entityClass' => Order::class,
            'active' => true,
            'successful' => true
        ],
    ];

    #[\Override]
    public function load(ObjectManager $manager): void
    {
        $transactionExpireHours = $this->container
            ->getParameter('oro_stripe.authorization_transaction_expiration_hours');

        $expireDate = (new \DateTime('now', new \DateTimeZone('UTC')))
            ->modify(sprintf('-%d hour', $transactionExpireHours + 1));

        foreach (self::$paymentTransactionsData as $identifier => $data) {
            $createdAt = clone $expireDate;

            if (in_array($identifier, [self::CAPTURE_TRANSACTION, self::ACTUAL_AUTHORIZATION_TRANSACTION])) {
                $createdAt = new \DateTime('now', new \DateTimeZone('UTC'));
            }

            $paymentTransaction = new PaymentTransaction();
            $paymentTransaction->setAmount($data['amount'])
                ->setCurrency($data['currency'])
                ->setAction($data['action'])
                ->setEntityIdentifier($data['entityIdentifier'])
                ->setPaymentMethod($data['paymentMethod'])
                ->setEntityClass($data['entityClass'])
                ->setActive($data['active'])
                ->setSuccessful($data['successful'])
                ->setCreatedAt($createdAt);

            $manager->persist($paymentTransaction);
            $this->setReference($identifier, $paymentTransaction);
        }

        $manager->flush();
    }

    #[\Override]
    public function setContainer(ContainerInterface $container = null): void
    {
        $this->container = $container;
    }
}
