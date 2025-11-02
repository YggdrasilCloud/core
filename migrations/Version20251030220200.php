<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add performance indexes for folder sorting and filtering operations.
 */
final class Version20251030220200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on folders table for efficient sorting and filtering (name, created_at)';
    }

    public function up(Schema $schema): void
    {
        // Index for sorting by name and searching by name
        $this->addSql('CREATE INDEX idx_folders_name ON folders(name)');

        // Index for sorting/filtering by creation date
        $this->addSql('CREATE INDEX idx_folders_created_at ON folders(created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_folders_name');
        $this->addSql('DROP INDEX IF EXISTS idx_folders_created_at');
    }
}
