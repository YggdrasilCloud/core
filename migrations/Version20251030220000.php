<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add taken_at field to photos table for EXIF capture date/time.
 */
final class Version20251030220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add taken_at nullable field and index to photos table for EXIF capture date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos ADD taken_at TIMESTAMP WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_photos_taken_at ON photos(taken_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_photos_taken_at');
        $this->addSql('ALTER TABLE photos DROP taken_at');
    }
}
