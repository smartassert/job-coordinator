<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261023094858 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . ' .is_ending.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD is_ending BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP is_ending');
    }
}
