-- RentOps MVP v1 — Schema Migration
-- Run once on a fresh database

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ─── Users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            CHAR(36)     NOT NULL PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Remember-me tokens ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS remember_tokens (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    CHAR(36)     NOT NULL,
    selector   VARCHAR(20)  NOT NULL UNIQUE,
    token_hash VARCHAR(64)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Properties ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS properties (
    id              CHAR(36)     NOT NULL PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    address         TEXT,
    total_rooms     INT          NOT NULL DEFAULT 22,
    default_due_day TINYINT      NOT NULL DEFAULT 5,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Rooms ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rooms (
    id          CHAR(36)                                               NOT NULL PRIMARY KEY,
    property_id CHAR(36)                                               NOT NULL,
    room_number VARCHAR(20)                                            NOT NULL,
    room_type   ENUM('single','sharing','dorm')                        NOT NULL DEFAULT 'single',
    base_rent   DECIMAL(10,2)                                          NOT NULL DEFAULT 0,
    status      ENUM('vacant','occupied','partially_occupied','maintenance') NOT NULL DEFAULT 'vacant',
    created_at  DATETIME                                               NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_property (property_id),
    INDEX idx_status (status),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tenants ──────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenants (
    id                CHAR(36)                                    NOT NULL PRIMARY KEY,
    full_name         VARCHAR(150)                                NOT NULL,
    phone             VARCHAR(20)                                 NOT NULL,
    email             VARCHAR(150)                                DEFAULT NULL,
    id_proof_type     ENUM('Aadhaar','PAN','Passport','Other')    NOT NULL DEFAULT 'Aadhaar',
    id_proof_number   VARCHAR(50)                                 DEFAULT NULL,
    emergency_contact VARCHAR(200)                                DEFAULT NULL,
    status            ENUM('active','vacated')                    NOT NULL DEFAULT 'active',
    created_at        DATETIME                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_phone  (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Tenancies ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tenancies (
    id                 CHAR(36)                  NOT NULL PRIMARY KEY,
    tenant_id          CHAR(36)                  NOT NULL,
    room_id            CHAR(36)                  NOT NULL,
    move_in_date       DATE                      NOT NULL,
    move_out_date      DATE                      DEFAULT NULL,
    agreed_rent        DECIMAL(10,2)             NOT NULL,
    security_deposit   DECIMAL(10,2)             NOT NULL DEFAULT 0,
    deposit_deduction  DECIMAL(10,2)             DEFAULT NULL,
    deposit_refund     DECIMAL(10,2)             DEFAULT NULL,
    rent_due_day       TINYINT                   NOT NULL DEFAULT 5,
    status             ENUM('active','closed')   NOT NULL DEFAULT 'active',
    created_at         DATETIME                  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id),
    INDEX idx_room   (room_id),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (room_id)   REFERENCES rooms(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Rent Invoices ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rent_invoices (
    id           CHAR(36)                                    NOT NULL PRIMARY KEY,
    tenancy_id   CHAR(36)                                    NOT NULL,
    period_month DATE                                        NOT NULL,
    amount_due   DECIMAL(10,2)                               NOT NULL,
    amount_paid  DECIMAL(10,2)                               NOT NULL DEFAULT 0,
    due_date     DATE                                        NOT NULL,
    status       ENUM('unpaid','partial','paid','overdue')   NOT NULL DEFAULT 'unpaid',
    created_at   DATETIME                                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenancy_period (tenancy_id, period_month),
    INDEX idx_status      (status),
    INDEX idx_due_date    (due_date),
    INDEX idx_period      (period_month),
    FOREIGN KEY (tenancy_id) REFERENCES tenancies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── Payments ─────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS payments (
    id           CHAR(36)                                      NOT NULL PRIMARY KEY,
    invoice_id   CHAR(36)                                      NOT NULL,
    amount       DECIMAL(10,2)                                 NOT NULL,
    payment_date DATE                                          NOT NULL,
    mode         ENUM('cash','UPI','bank_transfer','other')    NOT NULL DEFAULT 'cash',
    note         TEXT                                          DEFAULT NULL,
    recorded_by  VARCHAR(100)                                  NOT NULL DEFAULT 'Owner',
    created_at   DATETIME                                      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_invoice      (invoice_id),
    INDEX idx_payment_date (payment_date),
    FOREIGN KEY (invoice_id) REFERENCES rent_invoices(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
