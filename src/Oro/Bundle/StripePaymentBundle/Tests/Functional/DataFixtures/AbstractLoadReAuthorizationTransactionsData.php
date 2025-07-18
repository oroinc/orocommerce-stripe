<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Tests\Functional\DataFixtures;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

abstract class AbstractLoadReAuthorizationTransactionsData extends AbstractFixture implements
    DependentFixtureInterface,
    ContainerAwareInterface
{
    use ContainerAwareTrait;

    #[\Override]
    public function getDependencies(): array
    {
        return [LoadStripePaymentElementChannelData::class];
    }

    protected function createPaymentTransaction(
        ObjectManager $manager,
        string $reference,
        string $paymentMethod,
        string $action,
        bool $successful,
        bool $active,
        \DateTime $createdAt,
        array $transactionOptions = []
    ): void {
        $order = new Order();
        $order->setTotal(123.45);
        $order->setCurrency('USD');
        $manager->persist($order);
        $manager->flush();

        $paymentTransaction = new PaymentTransaction();
        $paymentTransaction->setPaymentMethod($paymentMethod);
        $paymentTransaction->setAction($action);
        $paymentTransaction->setSuccessful($successful);
        $paymentTransaction->setActive($active);
        $paymentTransaction->setCreatedAt($createdAt);
        $paymentTransaction->setAmount(123.45);
        $paymentTransaction->setCurrency('USD');
        $paymentTransaction->setEntityClass(Order::class);
        $paymentTransaction->setEntityIdentifier($order->getId());
        $paymentTransaction->setTransactionOptions($transactionOptions);

        $manager->persist($paymentTransaction);
        $this->setReference($reference, $paymentTransaction);
    }

    protected function getDateInRange(int $days, int $hours): \DateTime
    {
        $interval = sprintf('-%d days -%d hours', $days, $hours);

        return new \DateTime($interval, new \DateTimeZone('UTC'));
    }
}
