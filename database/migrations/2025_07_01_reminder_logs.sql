-- Migration: reminder_logs
-- Run once against your rentops database

CREATE TABLE IF NOT EXISTS `reminder_logs` (
  `id`              CHAR(36)     NOT NULL PRIMARY KEY,
  `channel`         ENUM('sms','email','whatsapp') NOT NULL,
  `recipient_type`  ENUM('all','selected','one')   NOT NULL DEFAULT 'selected',
  `recipients`      JSON         NOT NULL COMMENT 'Array of {id, name, phone, email}',
  `subject`         VARCHAR(255) DEFAULT NULL,
  `message`         TEXT         NOT NULL,
  `scheduled_at`    DATETIME     DEFAULT NULL,
  `sent_at`         DATETIME     DEFAULT NULL,
  `status`          ENUM('queued','sent','failed','scheduled') NOT NULL DEFAULT 'queued',
  `attachment_path` VARCHAR(500) DEFAULT NULL,
  `sent_count`      SMALLINT     NOT NULL DEFAULT 0,
  `fail_count`      SMALLINT     NOT NULL DEFAULT 0,
  `error_log`       TEXT         DEFAULT NULL,
  `created_by`      VARCHAR(100) DEFAULT NULL,
  `created_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_rl_status`    (`status`),
  INDEX `idx_rl_created`   (`created_at`),
  INDEX `idx_rl_scheduled` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
