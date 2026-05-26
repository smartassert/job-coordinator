<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\WorkerComponentState;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526164855 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . WorkerComponentState::class . '.isPending.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_component_state ADD is_pending BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE worker_component_state DROP is_pending');
    }
}
