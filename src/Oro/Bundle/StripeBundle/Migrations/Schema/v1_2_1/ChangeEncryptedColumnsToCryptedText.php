<?php

namespace Oro\Bundle\StripeBundle\Migrations\Schema\v1_2_1;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\SecurityBundle\DoctrineExtension\Dbal\Types\CryptedTextType;

/**
 * Converts legacy Stripe encrypted credential columns from crypted_string (VARCHAR(255)) to crypted_text (TEXT)
 * to fit AES-encrypted values that exceed 255 chars.
 */
class ChangeEncryptedColumnsToCryptedText implements Migration
{
    #[\Override]
    public function up(Schema $schema, QueryBag $queries): void
    {
        $table = $schema->getTable('oro_integration_transport');

        $this->changeColumnToCryptedText($table, 'stripe_api_secret_key');
        $this->changeColumnToCryptedText($table, 'stripe_signing_secret');
    }

    private function changeColumnToCryptedText(Table $table, string $columnName): void
    {
        if ($table->getColumn($columnName)->getType()->getName() === CryptedTextType::TYPE) {
            return;
        }

        $table->changeColumn($columnName, [
            'type' => Type::getType(CryptedTextType::TYPE),
            'length' => null,
            'comment' => '(DC2Type:' . CryptedTextType::TYPE . ')',
        ]);
    }
}
