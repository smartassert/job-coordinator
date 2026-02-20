<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260219124214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ' . Machine::class . ' .has_failed_state and .has_end_state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP has_failed_state');
        $this->addSql('ALTER TABLE machine DROP has_end_state');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD has_failed_state BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE machine ADD has_end_state BOOLEAN NOT NULL');
    }
}
