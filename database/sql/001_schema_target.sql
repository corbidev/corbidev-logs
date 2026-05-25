-- Target SQL schema for corbidev-logs (P-001)
-- Engine: MariaDB/MySQL (InnoDB)

CREATE TABLE IF NOT EXISTS projects (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_projects_slug (slug),
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO projects (id, slug, name, retention_days, is_active, created_at, updated_at)
VALUES (1, 'default', 'Default project', 30, 1, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    slug = VALUES(slug),
    name = VALUES(name),
    retention_days = VALUES(retention_days),
    is_active = VALUES(is_active),
    updated_at = NOW();

CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_prefix VARCHAR(12) DEFAULT NULL,
    label VARCHAR(150) NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    revoked_at DATETIME DEFAULT NULL,
    last_used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_api_tokens_token_hash (token_hash),
    KEY idx_api_tokens_project_id (project_id),
    KEY idx_api_tokens_expires_at (expires_at),
    KEY idx_api_tokens_revoked_at (revoked_at),
    KEY idx_api_tokens_prefix (token_prefix),
    PRIMARY KEY (id),
    CONSTRAINT fk_api_tokens_project_id FOREIGN KEY (project_id) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS logs (
    id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
    external_id VARCHAR(36) NOT NULL,
    project_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
    fingerprint VARCHAR(16) NOT NULL,
    request_id VARCHAR(100) NOT NULL,
    level VARCHAR(20) NOT NULL,
    http_status SMALLINT UNSIGNED NOT NULL,
    domain VARCHAR(100) NOT NULL,
    uri VARCHAR(1000) NOT NULL,
    method VARCHAR(10) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,
    env VARCHAR(50) NOT NULL,
    client VARCHAR(50) NOT NULL,
    message LONGTEXT NOT NULL,
    context_json JSON NOT NULL,
    extra_json JSON NOT NULL,
    ingestion_warnings_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    client_date DATETIME DEFAULT NULL,
    ip VARCHAR(45) DEFAULT NULL,
    UNIQUE KEY uniq_logs_external_id (external_id),
    KEY idx_logs_project_id (project_id),
    KEY idx_logs_created_at (created_at),
    KEY idx_logs_fingerprint (fingerprint),
    KEY idx_logs_request_id (request_id),
    KEY idx_logs_level (level),
    KEY idx_logs_env (env),
    KEY idx_logs_http_status (http_status),
    KEY idx_logs_project_created (project_id, created_at),
    KEY idx_logs_project_fingerprint (project_id, fingerprint),
    KEY idx_logs_project_request (project_id, request_id),
    PRIMARY KEY (id),
    CONSTRAINT fk_logs_project_id FOREIGN KEY (project_id) REFERENCES projects (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
