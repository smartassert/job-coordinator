<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\MachineActionFailure;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241010142439 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table for ' . MachineActionFailure::class;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE machine_action_failure (
                id VARCHAR(32) NOT NULL,
                action VARCHAR(255) NOT NULL, 
                type VARCHAR(255) NOT NULL, 
                context JSON DEFAULT NULL, 
                PRIMARY KEY(id)
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE machine_action_failure');
    }
}
