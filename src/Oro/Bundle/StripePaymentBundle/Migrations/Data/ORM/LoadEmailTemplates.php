<?php

namespace Oro\Bundle\StripePaymentBundle\Migrations\Data\ORM;

use Oro\Bundle\EmailBundle\Migrations\Data\ORM\AbstractHashEmailMigration;
use Oro\Bundle\MigrationBundle\Fixture\VersionedFixtureInterface;

/**
 * Loads email templates.
 */
class LoadEmailTemplates extends AbstractHashEmailMigration implements VersionedFixtureInterface
{
    #[\Override]
    protected function getEmailHashesToUpdate(): array
    {
        return [
            'stripe_payment_element_re_authorization_failed' => [
                '33474706db1781356a2fce3a13041a50', // 7.0.0.0
            ],
        ];
    }

    #[\Override]
    public function getVersion(): string
    {
        return '7.0.0.0';
    }

    #[\Override]
    public function getEmailsDir(): string
    {
        return $this->container->get('kernel')
            ->locateResource('@OroStripePaymentBundle/Migrations/Data/ORM/emails');
    }
}
