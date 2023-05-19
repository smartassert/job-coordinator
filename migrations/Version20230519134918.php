<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\RemoteRequest;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230519134918 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . RemoteRequest::class . '::failure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE remote_request ADD failure_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('
            ALTER TABLE remote_request 
                ADD CONSTRAINT FK_7F1C38F8BADC2069 
                FOREIGN KEY (failure_id) REFERENCES remote_request_failure (id) NOT DEFERRABLE INITIALLY IMMEDIATE
        ');
        $this->addSql('CREATE INDEX IDX_7F1C38F8BADC2069 ON remote_request (failure_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE remote_request DROP CONSTRAINT FK_7F1C38F8BADC2069');
        $this->addSql('DROP INDEX IDX_7F1C38F8BADC2069');
        $this->addSql('ALTER TABLE remote_request DROP failure_id');
    }
}
