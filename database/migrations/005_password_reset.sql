-- RentOps Migration 005 — password reset tokens

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id         BIGINT       NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    CHAR(36)     NOT NULL,
    token_hash VARCHAR(64)  NOT NULL UNIQUE,
    expires_at DATETIME     NOT NULL,
    used_at    DATETIME     DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token   (token_hash),
    INDEX idx_user    (user_id),
    INDEX idx_expires (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
