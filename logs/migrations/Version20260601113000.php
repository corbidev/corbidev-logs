<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260601113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Introduce domains table and transitional domain_id columns on api_tokens, logs, and api_consumer.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS domains (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX uniq_domains_slug (slug),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql(<<<'SQL'
INSERT INTO domains (id, slug, name, retention_days, is_active, created_at, updated_at)
SELECT p.id, p.slug, p.name, p.retention_days, p.is_active, p.created_at, p.updated_at
FROM projects p
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug),
    name = VALUES(name),
    retention_days = VALUES(retention_days),
    is_active = VALUES(is_active),
    updated_at = VALUES(updated_at)
SQL);

        $this->addSql('ALTER TABLE api_tokens ADD domain_id BIGINT UNSIGNED DEFAULT NULL AFTER project_id');
        $this->addSql('UPDATE api_tokens SET domain_id = project_id WHERE domain_id IS NULL');
        $this->addSql('ALTER TABLE api_tokens ADD INDEX idx_api_tokens_domain_id (domain_id)');
        $this->addSql('ALTER TABLE api_tokens ADD CONSTRAINT fk_api_tokens_domain_id FOREIGN KEY (domain_id) REFERENCES domains (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE logs ADD domain_id BIGINT UNSIGNED DEFAULT NULL AFTER project_id');
        $this->addSql('UPDATE logs SET domain_id = project_id WHERE domain_id IS NULL');
        $this->addSql('ALTER TABLE logs ADD INDEX idx_logs_domain_id (domain_id)');
        $this->addSql('ALTER TABLE logs ADD CONSTRAINT fk_logs_domain_id FOREIGN KEY (domain_id) REFERENCES domains (id)');

        $this->addSql('ALTER TABLE api_consumer ADD domain_id BIGINT UNSIGNED DEFAULT NULL AFTER id');
        $this->addSql('UPDATE api_consumer SET domain_id = (SELECT id FROM domains ORDER BY id ASC LIMIT 1) WHERE domain_id IS NULL');
        $this->addSql('ALTER TABLE api_consumer MODIFY domain_id BIGINT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE api_consumer ADD INDEX idx_api_consumer_domain_id (domain_id)');
        $this->addSql('ALTER TABLE api_consumer ADD CONSTRAINT fk_api_consumer_domain_id FOREIGN KEY (domain_id) REFERENCES domains (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_consumer DROP FOREIGN KEY fk_api_consumer_domain_id');
        $this->addSql('ALTER TABLE api_consumer DROP INDEX idx_api_consumer_domain_id');
        $this->addSql('ALTER TABLE api_consumer DROP COLUMN domain_id');

        $this->addSql('ALTER TABLE logs DROP FOREIGN KEY fk_logs_domain_id');
        $this->addSql('ALTER TABLE logs DROP INDEX idx_logs_domain_id');
        $this->addSql('ALTER TABLE logs DROP COLUMN domain_id');

        $this->addSql('ALTER TABLE api_tokens DROP FOREIGN KEY fk_api_tokens_domain_id');
        $this->addSql('ALTER TABLE api_tokens DROP INDEX idx_api_tokens_domain_id');
        $this->addSql('ALTER TABLE api_tokens DROP COLUMN domain_id');

        $this->addSql('DROP TABLE IF EXISTS domains');
    }
}
