<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\ReAuthorization\Provider;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\AbstractQuery;
use Oro\Bundle\BatchBundle\ORM\Query\BufferedIdentityQueryResultIterator;
use Oro\Bundle\PaymentBundle\Entity\Repository\PaymentTransactionRepository;
use Oro\Bundle\PaymentBundle\Method\PaymentMethodInterface;
use Oro\Bundle\PaymentBundle\Method\Provider\PaymentMethodProviderInterface;

/**
 * Provides the IDs of authorization payment transactions that are about to expire.
 */
class ReAuthorizePaymentTransactionsProvider implements ReAuthorizePaymentTransactionsProviderInterface
{
    private int $bufferSize = 200;

    private int $createdEarlierThan = 164; // 6d 20h

    private int $createdLaterThan = 168; // 7d

    public function __construct(
        private readonly PaymentMethodProviderInterface $paymentMethodProvider,
        private readonly PaymentTransactionRepository $paymentTransactionRepository
    ) {
    }

    public function setBufferSize(int $bufferSize): void
    {
        $this->bufferSize = $bufferSize;
    }

    public function setCreatedEarlierThan(int $hours): void
    {
        $this->createdEarlierThan = $hours;
    }

    public function setCreatedLaterThan(int $hours): void
    {
        $this->createdLaterThan = $hours;
    }

    #[\Override]
    public function getPaymentTransactionIds(): iterable
    {
        $paymentMethods = $this->paymentMethodProvider->getPaymentMethods();
        $paymentMethodIdentifiers = [];
        foreach ($paymentMethods as $paymentMethod) {
            $paymentMethodIdentifiers[] = $paymentMethod->getIdentifier();
        }

        if (!$paymentMethodIdentifiers) {
            return new \EmptyIterator();
        }

        $queryBuilder = $this->paymentTransactionRepository->createQueryBuilder('paymentTransaction');
        $queryBuilder
            ->select('paymentTransaction.id')
            ->andWhere($queryBuilder->expr()->in('paymentTransaction.paymentMethod', ':paymentMethodIdentifiers'))
            ->setParameter('paymentMethodIdentifiers', $paymentMethodIdentifiers, Connection::PARAM_STR_ARRAY)
            ->andWhere($queryBuilder->expr()->eq('paymentTransaction.action', ':paymentAction'))
            ->setParameter('paymentAction', PaymentMethodInterface::AUTHORIZE, Types::STRING)
            ->andWhere($queryBuilder->expr()->eq('paymentTransaction.active', ':isActive'))
            ->setParameter('isActive', true, Types::BOOLEAN)
            ->andWhere($queryBuilder->expr()->eq('paymentTransaction.successful', ':isSuccessful'))
            ->setParameter('isSuccessful', true, Types::BOOLEAN)
            ->andWhere($queryBuilder->expr()->gte('paymentTransaction.createdAt', ':dateFrom'))
            ->setParameter(
                'dateFrom',
                new \DateTime(sprintf('- %d hours', $this->createdLaterThan), new \DateTimeZone('UTC')),
                Types::DATETIME_MUTABLE
            )
            ->andWhere($queryBuilder->expr()->lte('paymentTransaction.createdAt', ':dateTo'))
            ->setParameter(
                'dateTo',
                new \DateTime(sprintf('- %d hours', $this->createdEarlierThan), new \DateTimeZone('UTC')),
                Types::DATETIME_MUTABLE
            )
            ->orderBy('paymentTransaction.createdAt', 'ASC');

        return (new BufferedIdentityQueryResultIterator($queryBuilder))
            ->setHydrationMode(AbstractQuery::HYDRATE_SCALAR_COLUMN)
            ->setBufferSize($this->bufferSize);
    }
}
