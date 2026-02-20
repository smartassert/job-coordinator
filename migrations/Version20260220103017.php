<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\WorkerComponentState;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220103017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ' . WorkerComponentState::class . ' .is_end_state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_component_state DROP is_end_state');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_component_state ADD is_end_state BOOLEAN NOT NULL');
    }
}
