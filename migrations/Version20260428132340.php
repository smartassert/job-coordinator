<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ResultsJob;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428132340 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove ' . ResultsJob::class . '.token, add ' . ResultsJob::class . '.event_add_url.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE results_job ADD event_add_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE results_job DROP token');
    }

    public function down(Schema $schema): void
    {

        $this->addSql('ALTER TABLE results_job ADD token VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE results_job DROP event_add_url');
    }
}
