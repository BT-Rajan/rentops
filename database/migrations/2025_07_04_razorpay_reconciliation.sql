-- Migration: Razorpay link reconciliation + webhook support
-- Run once against your rentops database

ALTER TABLE `properties`
    ADD COLUMN IF NOT EXISTS `razorpay_webhook_secret` VARCHAR(200) DEFAULT NULL COMMENT 'AES-256-CBC encrypted — for webhook signature verification';

ALTER TABLE `rent_invoices`
    ADD COLUMN IF NOT EXISTS `razorpay_link_id`        VARCHAR(100) DEFAULT NULL COMMENT 'Razorpay plink_xxx ID — needed to cancel/expire a stale link',
    ADD COLUMN IF NOT EXISTS `razorpay_link_amount`     DECIMAL(10,2) DEFAULT NULL COMMENT 'Balance amount the link was created for — used to detect staleness',
    ADD COLUMN IF NOT EXISTS `razorpay_link_status`     ENUM('created','paid','cancelled','expired') DEFAULT NULL;

-- Idempotency log for Razorpay webhook events (avoid double-processing on retries)
CREATE TABLE IF NOT EXISTS `razorpay_webhook_events` (
    `id`           CHAR(36)     NOT NULL PRIMARY KEY,
    `event_id`     VARCHAR(100) NOT NULL UNIQUE COMMENT 'Razorpay event ID (x-razorpay-event-id header or payload.id)',
    `event_type`   VARCHAR(60)  NOT NULL,
    `invoice_id`   CHAR(36)     DEFAULT NULL,
    `payload`      JSON         DEFAULT NULL,
    `processed_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
