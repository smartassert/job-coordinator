<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\ResultsJob;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260212184944 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . ResultsJob::class . '.state_is_ended and state_is_succeeded.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE results_job ADD state_is_ended BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE results_job ADD state_is_succeeded BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE results_job DROP state_is_ended');
        $this->addSql('ALTER TABLE results_job DROP state_is_succeeded');
    }
}
