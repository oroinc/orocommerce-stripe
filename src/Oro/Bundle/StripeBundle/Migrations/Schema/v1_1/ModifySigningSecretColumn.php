<?php

namespace Oro\Bundle\StripeBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Add crypted_string comment for stripe_signing_secret column.
 */
class ModifySigningSecretColumn implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_integration_transport');

        if ($table->hasColumn('stripe_signing_secret')) {
            $table->modifyColumn('stripe_signing_secret', [
                'comment' => '(DC2Type:crypted_string)'
            ]);
        }
    }
}
