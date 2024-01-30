<?php

namespace Oro\Bundle\StripeBundle\Migrations\Schema\v1_2;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class AddAppleGooglePayColumns implements Migration
{
    public function up(Schema $schema, QueryBag $queries)
    {
        $this->createOroStripeTransportAppleGooglePayLabelTable($schema);
        $this->addOroStripeTransportAppleGooglePayLabelForeignKeys($schema);
    }

    private function createOroStripeTransportAppleGooglePayLabelTable(Schema $schema): void
    {
        if (!$schema->hasTable('oro_stripe_transport_apple_google_pay_label')) {
            $table = $schema->createTable('oro_stripe_transport_apple_google_pay_label');
            $table->addColumn('transport_id', 'integer', []);
            $table->addColumn('localized_value_id', 'integer', []);
            $table->addUniqueIndex(
                ['localized_value_id'],
                'oro_stripe_transport_apple_google_pay_label_localized_value_id'
            );
            $table->setPrimaryKey(['transport_id', 'localized_value_id']);
            $table->addIndex(['transport_id'], 'oro_stripe_transport_apple_google_pay_label_transport_id', []);
        }
    }

    private function addOroStripeTransportAppleGooglePayLabelForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_stripe_transport_apple_google_pay_label');

        if (!$table->hasForeignKey('transport_id')) {
            $table->addForeignKeyConstraint(
                $schema->getTable('oro_integration_transport'),
                ['transport_id'],
                ['id'],
                ['onUpdate' => null, 'onDelete' => 'CASCADE']
            );
        }

        if (!$table->hasForeignKey('localized_value_id')) {
            $table->addForeignKeyConstraint(
                $schema->getTable('oro_fallback_localization_val'),
                ['localized_value_id'],
                ['id'],
                ['onUpdate' => null, 'onDelete' => 'CASCADE']
            );
        }
    }
}
