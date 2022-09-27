<?php

namespace Oro\Bundle\StripeBundle\Provider;

use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\BatchBundle\ORM\Query\BufferedIdentityQueryResultIterator;
use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\OrderBundle\Entity\Order;
use Oro\Bundle\PaymentBundle\Entity\PaymentTransaction;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Provider\PaymentTransactionProvider;

/**
 * Encapsulates logic to get entities and prepare transactions for these entities.
 */
class EntitiesTransactionsProvider
{
    private const AUTHORIZATION_TRANSACTION_EXPIRATION_HOURS = 164; // 6d20h

    private DoctrineHelper $doctrineHelper;
    private PaymentTransactionProvider $paymentTransactionProvider;
    private int $authorizationTransactionExpirationHours;

    public function __construct(
        DoctrineHelper $doctrineHelper,
        PaymentTransactionProvider $paymentTransactionProvider,
        int $authorizationTransactionExpirationHours = self::AUTHORIZATION_TRANSACTION_EXPIRATION_HOURS
    ) {
        $this->doctrineHelper = $doctrineHelper;
        $this->paymentTransactionProvider = $paymentTransactionProvider;
        $this->authorizationTransactionExpirationHours = $authorizationTransactionExpirationHours;
    }

    public function getTransactionsForMultipleEntities(PaymentTransaction $sourceTransaction): array
    {
        $entities = $this->getEntities($sourceTransaction);
        $entitiesTransactions = [];

        foreach ($entities as $entity) {
            $subPaymentTransaction = $this->paymentTransactionProvider->createPaymentTransaction(
                $sourceTransaction->getPaymentMethod(),
                PaymentMethodInterface::PURCHASE,
                $entity
            );

            $subPaymentTransaction->setAmount($entity->getTotal());
            $subPaymentTransaction->setCurrency($entity->getCurrency());
            $subPaymentTransaction->setTransactionOptions($sourceTransaction->getTransactionOptions());
            $subPaymentTransaction->setSourcePaymentTransaction($sourceTransaction);

            $entitiesTransactions []= $subPaymentTransaction;
        }

        return $entitiesTransactions;
    }

    public function hasEntities(PaymentTransaction $paymentTransaction): bool
    {
        $entity = $this->getSourceEntity($paymentTransaction);

        if ($entity instanceof Order && !$entity->getSubOrders()->isEmpty()) {
            return true;
        }

        return false;
    }

    public function hasExpiringAuthorizationTransactions(): bool
    {
        $qb = $this->getExpiringAuthorizeTransactionsQB();
        $qb->resetDQLPart('select')
            ->select('COUNT(*)');

        return $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * @param array $paymentMethods
     * @return \Iterator
     */
    public function getExpiringAuthorizationTransactions(array $paymentMethods): \Iterator
    {
        $qb = $this->getExpiringAuthorizeTransactionsQB($paymentMethods);

        return new BufferedIdentityQueryResultIterator($qb);
    }

    private function getEntities(PaymentTransaction $paymentTransaction): iterable
    {
        $entity = $this->getSourceEntity($paymentTransaction);

        if ($entity instanceof Order && !$entity->getSubOrders()->isEmpty()) {
            return $entity->getSubOrders();
        }

        return [$entity];
    }

    protected function getSourceEntity(PaymentTransaction $paymentTransaction): ?object
    {
        return $this->doctrineHelper->getEntityReference(
            $paymentTransaction->getEntityClass(),
            $paymentTransaction->getEntityIdentifier()
        );
    }

    private function getExpiringAuthorizeTransactionsQB(array $paymentMethods): QueryBuilder
    {
        $repo = $this->doctrineHelper->getEntityRepository(PaymentTransaction::class);
        $qb = $repo->createQueryBuilder('pt');
        $qb
            ->where(
                $qb->expr()->andX(
                    $qb->expr()->eq('pt.active', ':isActive'),
                    $qb->expr()->eq('pt.successful', ':isSuccessful'),
                    $qb->expr()->eq('pt.action', ':action'),
                    $qb->expr()->lte('pt.createdAt', ':actionDate'),
                    $qb->expr()->in('pt.paymentMethod', ':paymentMethods')
                )
            )
            ->setParameters([
                'isActive' => true,
                'isSuccessful' => true,
                'action' => PaymentMethodInterface::AUTHORIZE,
                'actionDate' => new \DateTime(
                    sprintf('- %d hours', $this->authorizationTransactionExpirationHours),
                    new \DateTimeZone('UTC')
                ),
                'paymentMethods' => $paymentMethods
            ]);

        return $qb;
    }
}
