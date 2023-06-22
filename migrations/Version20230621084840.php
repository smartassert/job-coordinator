<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230621084840 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . Machine::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE machine (
                job_id VARCHAR(32) NOT NULL, 
                state VARCHAR(128) NOT NULL, 
                state_category VARCHAR(128) NOT NULL,
                ip VARCHAR(255) DEFAULT NULL, 
                PRIMARY KEY(job_id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE machine');
    }
}
