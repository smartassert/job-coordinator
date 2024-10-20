<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use App\Entity\SerializedSuite;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241020182212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . SerializedSuite::class . '.is_prepared, has_end_state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE serialized_suite ADD is_prepared BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE serialized_suite ADD has_end_state BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE serialized_suite DROP is_prepared');
        $this->addSql('ALTER TABLE serialized_suite DROP has_end_state');
    }
}
