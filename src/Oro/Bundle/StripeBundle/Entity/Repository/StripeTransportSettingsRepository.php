<?php

namespace Oro\Bundle\StripeBundle\Entity\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Oro\Bundle\SecurityBundle\ORM\Walker\AclHelper;

/**
 * Repository for StripeTransportSettings entity
 */
class StripeTransportSettingsRepository extends ServiceEntityRepository
{
    private ?AclHelper $aclHelper = null;

    public function setAclHelper(AclHelper $aclHelper): self
    {
        $this->aclHelper = $aclHelper;

        return $this;
    }

    public function getEnabledSettingsByType(string $type): array
    {
        $qb = $this->getEnabledSettingsByTypeQueryBuilder($type);

        return $this->aclHelper->apply($qb)->getResult();
    }

    public function getEnabledMonitoringSettingsByType(string $type): array
    {
        $qb = $this->getEnabledSettingsByTypeQueryBuilder($type);
        $qb
            ->andWhere($qb->expr()->eq('settings.userMonitoring', ':monitoring'))
            ->setParameter('monitoring', true);

        return $this->aclHelper->apply($qb)->getResult();
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
