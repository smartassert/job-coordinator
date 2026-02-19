<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218174625 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.state_is_ended and .state_is_succeeded.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD state_is_ended BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE machine ADD state_is_succeeded BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP state_is_ended');
        $this->addSql('ALTER TABLE machine DROP state_is_succeeded');
    }
}
