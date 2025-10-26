<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020214418 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace storage_path/thumbnail_path with storage_key/storage_adapter/thumbnail_key for DSN-based file storage';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE photos ADD storage_key VARCHAR(1024) NOT NULL');
        $this->addSql('ALTER TABLE photos ADD storage_adapter VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE photos ADD thumbnail_key VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE photos DROP storage_path');
        $this->addSql('ALTER TABLE photos DROP thumbnail_path');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // Note: CREATE SCHEMA removed for SQLite compatibility
        $this->addSql('ALTER TABLE photos ADD storage_path VARCHAR(500) NOT NULL');
        $this->addSql('ALTER TABLE photos ADD thumbnail_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE photos DROP storage_key');
        $this->addSql('ALTER TABLE photos DROP storage_adapter');
        $this->addSql('ALTER TABLE photos DROP thumbnail_key');
    }
}
