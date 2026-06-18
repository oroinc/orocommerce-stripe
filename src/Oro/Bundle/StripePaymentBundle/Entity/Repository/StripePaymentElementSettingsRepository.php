<?php

declare(strict_types=1);

namespace Oro\Bundle\StripePaymentBundle\Entity\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;
use Oro\Bundle\StripePaymentBundle\Entity\StripePaymentElementSettings;

/**
 * Doctrine entity repository of {@see StripePaymentElementSettings} entity.
 */
class StripePaymentElementSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, string $entityClass, private readonly AclHelper $aclHelper)
    {
        parent::__construct($registry, $entityClass);
    }

    /**
     * @return array<StripePaymentElementSettings>
     */
    public function findEnabledSettings(): array
    {
        $qb = $this->createQueryBuilder('settings');
        $qb
            ->innerJoin('settings.channel', 'channel')
            ->andWhere($qb->expr()->eq('channel.enabled', ':channelEnabled'))
            ->setParameter('channelEnabled', true);

        return $this->aclHelper->apply($qb)->getResult();
    }

    public function hasEnabledSettings(): bool
    {
        $qb = $this->createQueryBuilder('settings');
        $qb
            ->select('1')
            ->innerJoin('settings.channel', 'channel')
            ->andWhere($qb->expr()->eq('channel.enabled', ':channelEnabled'))
            ->setParameter('channelEnabled', true)
            ->setMaxResults(1);

        return $this->aclHelper->apply($qb)->getOneOrNullResult() !== null;
    }
}
