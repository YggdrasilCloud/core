<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251011151500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add thumbnail_path column to photos table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos ADD thumbnail_path VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE photos DROP thumbnail_path');
    }
}
