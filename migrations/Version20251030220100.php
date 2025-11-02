<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add performance indexes for photo sorting and filtering operations.
 */
final class Version20251030220100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add indexes on photos table for efficient sorting and filtering (file_name, mime_type, size_in_bytes, uploaded_at)';
    }

    public function up(Schema $schema): void
    {
        // Index for sorting by filename and searching by name
        $this->addSql('CREATE INDEX idx_photos_file_name ON photos(file_name)');

        // Index for filtering by MIME type (image/jpeg, image/png, etc.)
        $this->addSql('CREATE INDEX idx_photos_mime_type ON photos(mime_type)');

        // Index for sorting/filtering by file size
        $this->addSql('CREATE INDEX idx_photos_size_in_bytes ON photos(size_in_bytes)');

        // Index for sorting/filtering by upload date
        $this->addSql('CREATE INDEX idx_photos_uploaded_at ON photos(uploaded_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_photos_file_name');
        $this->addSql('DROP INDEX IF EXISTS idx_photos_mime_type');
        $this->addSql('DROP INDEX IF EXISTS idx_photos_size_in_bytes');
        $this->addSql('DROP INDEX IF EXISTS idx_photos_uploaded_at');
    }
}
