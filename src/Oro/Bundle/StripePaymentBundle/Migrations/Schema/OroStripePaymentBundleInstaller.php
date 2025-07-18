<?php

namespace Oro\Bundle\StripePaymentBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class OroStripePaymentBundleInstaller implements Installation
{
    #[\Override]
    public function getMigrationVersion(): string
    {
        return 'v7_0_0_0';
    }

    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->createStripePaymentElementIntegration($schema, $queries);
    }

    private function createStripePaymentElementIntegration(Schema $schema, QueryBag $queries): void
    {
        $this->createStripePaymentElementSettingsColumns($schema);

        $this->createStripePaymentElementSettingsPaymentMethodLabelTable($schema);
        $this->createStripePaymentElementSettingsPaymentMethodShortLabelTable($schema);

        $this->addStripePaymentElementSettingsPaymentMethodLabelForeignKeys($schema);
        $this->addStripePaymentElementSettingsPaymentMethodShortLabelForeignKeys($schema);
    }

    private function createStripePaymentElementSettingsColumns(Schema $schema): void
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('stripe_payment_element_api_public_key', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('stripe_payment_element_api_secret_key', 'string', [
            'notnull' => false,
            'length' => 255,
            'comment' => '(DC2Type:crypted_string)',
        ]);
        $table->addColumn('stripe_payment_element_payment_method_name', 'string', [
            'notnull' => false,
            'length' => 255,
        ]);
        $table->addColumn('stripe_payment_element_webhook_access_id', 'guid', [
            'notnull' => false,
            'comment' => '(DC2Type:guid)',
        ]);
        $table->addColumn('stripe_payment_element_webhook_stripe_id', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('stripe_payment_element_webhook_secret', 'string', [
            'notnull' => false,
            'length' => 255,
            'comment' => '(DC2Type:crypted_string)',
        ]);
        $table->addColumn('stripe_payment_element_capture_method', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('stripe_payment_element_re_authorization_enabled', 'boolean', [
            'notnull' => false,
            'default' => false,
        ]);
        $table->addColumn('stripe_payment_element_re_authorization_email', 'string', ['notnull' => false]);
        $table->addColumn('stripe_payment_element_user_monitoring_enabled', 'boolean', [
            'notnull' => false,
            'default' => false,
        ]);
    }

    private function createStripePaymentElementSettingsPaymentMethodLabelTable(Schema $schema): void
    {
        if (!$schema->hasTable('oro_stripe_payment_element_payment_method_label')) {
            $table = $schema->createTable('oro_stripe_payment_element_payment_method_label');
            $table->addColumn('transport_id', 'integer', []);
            $table->addColumn('localized_value_id', 'integer', []);
            $table->addUniqueIndex(
                ['localized_value_id'],
                'oro_stripe_payment_element_payment_method_label_localized_value_id'
            );
            $table->setPrimaryKey(['transport_id', 'localized_value_id']);
            $table->addIndex(['transport_id'], 'oro_stripe_payment_element_payment_method_label_transport_id', []);
        }
    }

    private function createStripePaymentElementSettingsPaymentMethodShortLabelTable(Schema $schema): void
    {
        if (!$schema->hasTable('oro_stripe_payment_element_payment_method_short_label')) {
            $table = $schema->createTable('oro_stripe_payment_element_payment_method_short_label');
            $table->addColumn('transport_id', 'integer', []);
            $table->addColumn('localized_value_id', 'integer', []);
            $table->addUniqueIndex(
                ['localized_value_id'],
                'oro_stripe_payment_element_payment_method_short_label_localized_value_id'
            );
            $table->setPrimaryKey(['transport_id', 'localized_value_id']);
            $table->addIndex(['transport_id'], 'oro_stripe_payment_element_payment_method_short_label_transport_id');
        }
    }

    private function addStripePaymentElementSettingsPaymentMethodLabelForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_stripe_payment_element_payment_method_label');

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

    private function addStripePaymentElementSettingsPaymentMethodShortLabelForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_stripe_payment_element_payment_method_short_label');

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
