<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260514141830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.isReady.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD is_ready BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP is_ready');
    }
}
