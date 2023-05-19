<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\RemoteRequestFailure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230519134455 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ' . RemoteRequestFailure::class . ' entity';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE remote_request_failure (
                id VARCHAR(32) NOT NULL, 
                type VARCHAR(64) NOT NULL, 
                code SMALLINT NOT NULL, 
                message TEXT DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE remote_request_failure');
    }
}
