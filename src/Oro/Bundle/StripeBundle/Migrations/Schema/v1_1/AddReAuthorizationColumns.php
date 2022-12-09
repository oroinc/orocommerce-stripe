<?php

namespace Oro\Bundle\StripeBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Add re authorization columns to oro_integration_transport table.
 */
class AddReAuthorizationColumns implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_integration_transport');

        if (!$table->hasColumn('stripe_enable_re_authorize')) {
            $table->addColumn('stripe_enable_re_authorize', 'boolean', ['notnull' => false, 'default' => false]);
        }

        if (!$table->hasColumn('stripe_re_authorization_error_email')) {
            $table->addColumn('stripe_re_authorization_error_email', 'string', ['notnull' => false]);
        }
    }
}
