<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ResultsJob;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260612104616 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . ResultsJob::class . '.hasEvents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE results_job ADD has_events BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE results_job DROP has_events');
    }
}
