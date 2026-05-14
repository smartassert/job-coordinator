<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513133512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.isActive.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD is_active BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP is_active');
    }
}
