<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Job;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117153121 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename ' . Job::class . '::token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job RENAME COLUMN token TO user_security_token');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job RENAME COLUMN user_security_token TO token');
    }
}
