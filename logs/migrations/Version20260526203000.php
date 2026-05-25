<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260526203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create api_consumer table used by admin consumer management.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE api_consumer (
    id INT UNSIGNED AUTO_INCREMENT NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL,
    UNIQUE INDEX UNIQ_70E9DB3CEB518B14 (identifier),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE api_consumer');
    }
}
