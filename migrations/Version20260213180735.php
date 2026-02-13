<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\SerializedSuite;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213180735 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . SerializedSuite::class . '.state_is_ended and .state_is_succeeded.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE serialized_suite ADD state_is_ended BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE serialized_suite ADD state_is_succeeded BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE serialized_suite DROP state_is_ended');
        $this->addSql('ALTER TABLE serialized_suite DROP state_is_succeeded');
    }
}
