-- Assam Motors ERP
-- Phase 4: additive normalized OSL Purchase line table.
-- Designed to attach multiple OSL rows to one existing purchase header.

CREATE TABLE IF NOT EXISTS osl_purchase_line_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    purchase_id BIGINT UNSIGNED NOT NULL,
    line_no INT UNSIGNED NOT NULL,

    osl_master_id BIGINT UNSIGNED NULL,
    osl_code VARCHAR(80) NULL,
    osl_description VARCHAR(255) NOT NULL,

    allocation_type VARCHAR(24) NOT NULL DEFAULT 'UNALLOCATED',
    job_card_id BIGINT UNSIGNED NULL,
    job_card_no VARCHAR(80) NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    vehicle_reg_no VARCHAR(40) NULL,

    qty DECIMAL(12,3) NOT NULL DEFAULT 1.000,
    rate DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    discount_percent DECIMAL(7,3) NOT NULL DEFAULT 0.000,
    tax_percent DECIMAL(7,3) NOT NULL DEFAULT 0.000,

    taxable_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,

    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_osl_purchase_line (purchase_id, line_no),
    KEY idx_osl_purchase_id (purchase_id),
    KEY idx_osl_job_card (job_card_id),
    KEY idx_osl_vehicle (vehicle_id),
    KEY idx_osl_master (osl_master_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
