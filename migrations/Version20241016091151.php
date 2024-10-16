<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241016091151 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.has_failed_state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD has_failed_state BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP has_failed_state');
    }
}
