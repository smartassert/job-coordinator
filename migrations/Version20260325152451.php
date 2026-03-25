<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\WorkerJobCreationFailure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325152451 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . WorkerJobCreationFailure::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE worker_job_creation_failure (
                id VARCHAR(32) NOT NULL, 
                stage VARCHAR(255) NOT NULL, 
                exception_class TEXT NOT NULL, 
                exception_code INT NOT NULL, 
                exception_message TEXT NOT NULL, 
                PRIMARY KEY (id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE worker_job_creation_failure');
    }
}
