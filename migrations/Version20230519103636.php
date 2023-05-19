<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\RemoteRequest;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230519103636 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ' . RemoteRequest::class . ' entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE remote_request (
                id VARCHAR(32) NOT NULL, 
                job_id VARCHAR(32) NOT NULL,
                type VARCHAR(64) NOT NULL, 
                state VARCHAR(64) DEFAULT NULL, 
                PRIMARY KEY(id))
        ');
        $this->addSql('CREATE INDEX type_idx ON remote_request (type)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE remote_request');
    }
}
