<?php

namespace Oro\Bundle\StripeBundle\Entity\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository for StripeTransportSettings entity
 */
class StripeTransportSettingsRepository extends EntityRepository
{
    public function getEnabledSettingsByType(string $type): array
    {
        return $this->getEnabledSettingsByTypeQueryBuilder($type)
            ->getQuery()
            ->getResult();
    }

    public function getEnabledMonitoringSettingsByType(string $type): array
    {
        $qb = $this->getEnabledSettingsByTypeQueryBuilder($type);

        return $qb->andWhere($qb->expr()->eq('settings.userMonitoring', ':monitoring'))
            ->setParameter('monitoring', true)
            ->getQuery()
            ->getResult();
    }

    private function getEnabledSettingsByTypeQueryBuilder(string $type): QueryBuilder
    {
        $qb = $this->createQueryBuilder('settings');
        $qb
            ->innerJoin('settings.channel', 'channel', Join::WITH, 'channel.type = :channelType')
            ->andWhere($qb->expr()->eq('channel.enabled', ':channelEnabled'))
            ->setParameter('channelType', $type)
            ->setParameter('channelEnabled', true);

        return $qb;
    }
}
