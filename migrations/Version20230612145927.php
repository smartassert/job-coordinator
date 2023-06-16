<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ResultsJob;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230612145927 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . ResultsJob::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE results_job (
                id VARCHAR(32) NOT NULL,
                 token VARCHAR(32) NOT NULL, 
                 state VARCHAR(128) NOT NULL, 
                 end_state VARCHAR(128) DEFAULT NULL,
                  PRIMARY KEY(id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE results_job');
    }
}
