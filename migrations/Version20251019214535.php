<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251019214535 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_id column to folders table for nested folders support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE folders ADD parent_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_FE370418727ACA70 ON folders (parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_FE370418727ACA70');
        $this->addSql('ALTER TABLE folders DROP parent_id');
    }
}
