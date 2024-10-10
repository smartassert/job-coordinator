<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241010143140 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.actionFailure';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD action_failure_id VARCHAR(32) DEFAULT NULL');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF84D97E4AA3 FOREIGN KEY (action_failure_id) REFERENCES machine_action_failure (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1505DF84D97E4AA3 ON machine (action_failure_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP CONSTRAINT FK_1505DF84D97E4AA3');
        $this->addSql('DROP INDEX UNIQ_1505DF84D97E4AA3');
        $this->addSql('ALTER TABLE machine DROP action_failure_id');
    }
}
