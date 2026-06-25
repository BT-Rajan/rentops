-- RentOps Migration 003 — rent change history
-- Tracks mid-tenancy rent adjustments; engine uses latest effective record

CREATE TABLE IF NOT EXISTS rent_changes (
    id           CHAR(36)     NOT NULL PRIMARY KEY,
    tenancy_id   CHAR(36)     NOT NULL,
    old_rent     DECIMAL(10,2) NOT NULL,
    new_rent     DECIMAL(10,2) NOT NULL,
    effective_from DATE        NOT NULL,
    note         VARCHAR(255) DEFAULT NULL,
    created_by   VARCHAR(100) NOT NULL DEFAULT 'Owner',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenancy (tenancy_id),
    FOREIGN KEY (tenancy_id) REFERENCES tenancies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
