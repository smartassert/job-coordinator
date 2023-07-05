<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\WorkerState;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230705145005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . WorkerState::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE worker_state (
                job_id VARCHAR(32) NOT NULL,
                 application_state VARCHAR(64) NOT NULL, 
                 compilation_state VARCHAR(64) NOT NULL, 
                 execution_state VARCHAR(64) NOT NULL,
                 event_delivery_state VARCHAR(64) NOT NULL, 
                 PRIMARY KEY(job_id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE worker_state');
    }
}
