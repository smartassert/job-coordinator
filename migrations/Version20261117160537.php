<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Job;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117160537 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Job::class . '::notify_token';
    }

    public function up(Schema $schema): void
    {
            $this->addSql('ALTER TABLE job ADD notify_token VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
            $this->addSql('ALTER TABLE job DROP notify_token');
    }
}
