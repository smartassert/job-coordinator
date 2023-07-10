<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\WorkerComponentState;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230710095417 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . WorkerComponentState::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE worker_component_state (
                id VARCHAR(32) NOT NULL,
                state VARCHAR(64) NOT NULL,
                is_end_state BOOLEAN NOT NULL,
                PRIMARY KEY(id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE worker_component_state');
    }
}
