<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Job;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20220920083238 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . Job::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE job (
                id VARCHAR(32) NOT NULL, 
                user_id VARCHAR(32) NOT NULL, 
                suite_id VARCHAR(32) NOT NULL, 
                results_token VARCHAR(32) DEFAULT NULL,
                serialized_suite_id VARCHAR(32) DEFAULT NULL,
                machine_ip_address VARCHAR(128) DEFAULT NULL,
                serialized_suite_state VARCHAR(128) DEFAULT NULL,
                maximum_duration_in_seconds INT NOT NULL,
                machine_state_category VARCHAR(128) DEFAULT NULL,
                results_job_request_state VARCHAR(128) DEFAULT NULL,
                serialized_suite_request_state VARCHAR(128) DEFAULT NULL,
                machine_request_state VARCHAR(128) DEFAULT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('CREATE INDEX user_idx ON job (user_id)');
        $this->addSql('CREATE INDEX user_suite_idx ON job (user_id, suite_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_FBD8E0F8325FD920 ON job (serialized_suite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job');
    }
}
