<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\MachineActionFailure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021100648 extends AbstractMigration
{
    public function getDescription(): string
    {
        return MachineActionFailure::class . '.context can no longer be null.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine_action_failure ALTER context SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine_action_failure ALTER context DROP NOT NULL');
    }
}
