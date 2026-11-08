<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

class Version202604131112 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            '
            CREATE TABLE translation (
                id VARCHAR(36) NOT NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id VARCHAR(36) NOT NULL,
                field VARCHAR(255) NOT NULL,
                locale VARCHAR(10) NOT NULL,
                translation TEXT NOT NULL,
                created_at TIMESTAMP(6) NOT NULL,
                updated_at TIMESTAMP(6) DEFAULT NULL,
                deleted_at TIMESTAMP(6) DEFAULT NULL,
                PRIMARY KEY(id)
            ) '
        );

        $this->addSql('CREATE INDEX idx_translation_lookup ON translation (entity_type, entity_id, field, locale)');
        $this->addSql('CREATE INDEX idx_translation_entity ON translation (entity_type, entity_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE translation');
    }
}
