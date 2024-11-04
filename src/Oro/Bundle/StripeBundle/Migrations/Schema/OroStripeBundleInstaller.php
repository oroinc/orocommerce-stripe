<?php

namespace Oro\Bundle\StripeBundle\Migrations\Schema;

use Doctrine\DBAL\Schema\Schema;
use Oro\Bundle\MigrationBundle\Migration\Installation;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

/**
 * Create new and update existing DB tables to store information related to Stripe integration.
 */
class OroStripeBundleInstaller implements Installation
{
    #[\Override]
    public function getMigrationVersion(): string
    {
        return 'v1_2';
    }

    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $this->createStripeIntegrationTransport($schema);
        $this->createOroStripeTransportLabelTable($schema);
        $this->createOroStripeTransportShortLabelTable($schema);
        $this->createOroStripeTransportAppleGooglePayLabelTable($schema);

        $this->addOroStripeTransportLabelForeignKeys($schema);
        $this->addOroStripeTransportShortLabelForeignKeys($schema);
        $this->addOroStripeTransportAppleGooglePayLabelForeignKeys($schema);
    }

    private function createStripeIntegrationTransport(Schema $schema): void
    {
        $table = $schema->getTable('oro_integration_transport');
        $table->addColumn('stripe_api_public_key', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('stripe_api_secret_key', 'string', [
            'notnull' => false,
            'length' => 255,
            'comment' => '(DC2Type:crypted_string)'
        ]);
        $table->addColumn('stripe_signing_secret', 'string', [
            'notnull' => false,
            'length' => 255,
            'comment' => '(DC2Type:crypted_string)'
        ]);
        $table->addColumn('stripe_payment_action', 'string', ['notnull' => false, 'length' => 255]);
        $table->addColumn('stripe_user_monitoring', 'boolean', ['notnull' => false, 'default' => false]);
        $table->addColumn('stripe_enable_re_authorize', 'boolean', ['notnull' => false, 'default' => false]);
        $table->addColumn('stripe_re_authorization_error_email', 'string', ['notnull' => false]);
    }

    private function createOroStripeTransportLabelTable(Schema $schema): void
    {
        if (!$schema->hasTable('oro_stripe_transport_label')) {
            $table = $schema->createTable('oro_stripe_transport_label');
            $table->addColumn('transport_id', 'integer');
            $table->addColumn('localized_value_id', 'integer');
            $table->setPrimaryKey(['transport_id', 'localized_value_id']);
            $table->addUniqueIndex(['localized_value_id'], 'oro_stripe_transport_label_localized_value_id');
            $table->addIndex(['transport_id'], 'oro_stripe_transport_label_transport_id');
        }
    }

    private function addOroStripeTransportLabelForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_stripe_transport_label');
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

    private function createOroStripeTransportShortLabelTable(Schema $schema): void
    {
        if (!$schema->hasTable('oro_stripe_transport_short_label')) {
            $table = $schema->createTable('oro_stripe_transport_short_label');
            $table->addColumn('transport_id', 'integer');
            $table->addColumn('localized_value_id', 'integer');
            $table->setPrimaryKey(['transport_id', 'localized_value_id']);
            $table->addUniqueIndex(['localized_value_id'], 'oro_stripe_transport_short_label_localized_value_id');
            $table->addIndex(['transport_id'], 'oro_stripe_transport_short_label_transport_id');
        }
    }

    private function addOroStripeTransportShortLabelForeignKeys(Schema $schema): void
    {
        $table = $schema->getTable('oro_stripe_transport_short_label');
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

    private function createOroStripeTransportAppleGooglePayLabelTable(Schema $schema): void
    {
        if (!$schema->hasTable('oro_stripe_transport_apple_google_pay_label')) {
            $table = $schema->createTable('oro_stripe_transport_apple_google_pay_label');
            $table->addColumn('transport_id', 'integer');
            $table->addColumn('localized_value_id', 'integer');
            $table->setPrimaryKey(['transport_id', 'localized_value_id']);
            $table->addUniqueIndex(
                ['localized_value_id'],
                'oro_stripe_transport_apple_google_pay_label_localized_value_id'
            );
            $table->addIndex(['transport_id'], 'oro_stripe_transport_apple_google_pay_label_transport_id');
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
