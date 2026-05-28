<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Entity\Machine;
use App\Entity\ResultsJob;
use App\Entity\SerializedSuite;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526162852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ' . Machine::class . '.isPending, '
            . ResultsJob::class . '.isPending, '
            . SerializedSuite::class . '.isPending.'  ;
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine ADD is_pending BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE results_job ADD is_pending BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE serialized_suite ADD is_pending BOOLEAN NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE machine DROP is_pending');
        $this->addSql('ALTER TABLE results_job DROP is_pending');
        $this->addSql('ALTER TABLE serialized_suite DROP is_pending');
    }
}
