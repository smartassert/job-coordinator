<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260212184345 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change unique index name case (uniq_ -> UNIQ_)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_81b93dd1325fd920 RENAME TO UNIQ_81B93DD1BF396750');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER INDEX uniq_81b93dd1bf396750 RENAME TO uniq_81b93dd1325fd920');
    }
}
