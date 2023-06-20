<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\SerializedSuite;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230619093225 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . SerializedSuite::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE serialized_suite (
                job_id VARCHAR(32) NOT NULL,
                serialized_suite_id VARCHAR(32) NOT NULL,
                state VARCHAR(128) NOT NULL,
                PRIMARY KEY(job_id)
            )
        ');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_81B93DD1325FD920 ON serialized_suite (serialized_suite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE serialized_suite');
    }
}
