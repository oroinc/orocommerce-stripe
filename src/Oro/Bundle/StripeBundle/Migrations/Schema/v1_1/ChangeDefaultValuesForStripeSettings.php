<?php

namespace Oro\Bundle\StripeBundle\Migrations\Schema\v1_1;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Changes default values for the following settings:
 * - stripe_user_monitoring
 * - stripe_enable_re_authorize
 */
class ChangeDefaultValuesForStripeSettings implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_integration_transport');

        if ($table->hasColumn('stripe_user_monitoring')) {
            $table->changeColumn('stripe_user_monitoring', [
                'default' => false,
            ]);
        }
    }
}
