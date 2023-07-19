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
                id UUID NOT NULL, 
                user_id VARCHAR(32) NOT NULL, 
                suite_id VARCHAR(32) NOT NULL, 
                maximum_duration_in_seconds INT NOT NULL,
                PRIMARY KEY(id)
            )
        ');
        $this->addSql('COMMENT ON COLUMN job.id IS \'(DC2Type:ulid)\'');
        $this->addSql('CREATE INDEX user_idx ON job (user_id)');
        $this->addSql('CREATE INDEX user_suite_idx ON job (user_id, suite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job');
    }
}
