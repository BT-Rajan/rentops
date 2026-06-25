-- RentOps Migration 002 — Phase 2 additions
-- Run after 001_initial_schema.sql

SET NAMES utf8mb4;

-- ID proof file storage
ALTER TABLE tenants
    ADD COLUMN id_proof_file VARCHAR(300) DEFAULT NULL AFTER id_proof_number;

-- Overpayment flag on invoices
ALTER TABLE rent_invoices
    ADD COLUMN overpayment DECIMAL(10,2) DEFAULT 0 AFTER amount_paid,
    ADD COLUMN notes TEXT DEFAULT NULL AFTER status;

-- Import log table (audit trail)
CREATE TABLE IF NOT EXISTS import_logs (
    id          INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(200) NOT NULL,
    imported    INT          NOT NULL DEFAULT 0,
    skipped     INT          NOT NULL DEFAULT 0,
    errors      TEXT         DEFAULT NULL,
    imported_by VARCHAR(100) NOT NULL DEFAULT 'system',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
