<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ResultsJob;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261020161232 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . ResultsJob::class . '.event_notify_token, rename .event_add_url to .event_notify_url';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE results_job ADD event_notify_token VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE results_job RENAME COLUMN event_add_url TO event_notify_url');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE results_job ADD event_add_url VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE results_job DROP event_notify_url');
        $this->addSql('ALTER TABLE results_job DROP event_notify_token');
    }
}
