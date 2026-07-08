<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Job;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707155336 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Job::class . '.token';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job ADD token TEXT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job DROP token');
    }
}
