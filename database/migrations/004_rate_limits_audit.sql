-- RentOps Migration 004 — rate limiting + audit log

SET NAMES utf8mb4;

-- Rate limiter
CREATE TABLE IF NOT EXISTS rate_limits (
    id         BIGINT      NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `key`      VARCHAR(120) NOT NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_key_time (`key`, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log — every destructive action
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    actor       VARCHAR(100) NOT NULL DEFAULT 'system',
    action      VARCHAR(80)  NOT NULL,
    entity_type VARCHAR(60)  NOT NULL,
    entity_id   VARCHAR(36)  DEFAULT NULL,
    payload     JSON         DEFAULT NULL,
    ip          VARCHAR(45)  DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
