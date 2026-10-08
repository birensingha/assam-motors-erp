-- Assam Motors ERP
-- Phase 3: additive audit log for Job Card edits/workflow.

CREATE TABLE IF NOT EXISTS job_card_edit_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_card_id BIGINT UNSIGNED NOT NULL,
    job_card_no VARCHAR(80) NULL,
    event_type VARCHAR(48) NOT NULL,
    entity_type VARCHAR(32) NULL,
    entity_id VARCHAR(64) NULL,

    status_before VARCHAR(40) NULL,
    status_after VARCHAR(40) NULL,

    changed_by_user_id BIGINT UNSIGNED NULL,
    change_reason VARCHAR(255) NULL,
    before_json LONGTEXT NULL,
    after_json LONGTEXT NULL,

    event_at_local DATETIME(3) NOT NULL,
    event_at_utc DATETIME(3) NOT NULL,
    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_jc_audit_card_time (job_card_id, event_at_utc),
    KEY idx_jc_audit_job_no_time (job_card_no, event_at_utc),
    KEY idx_jc_audit_event_time (event_type, event_at_utc),
    KEY idx_jc_audit_user_time (changed_by_user_id, event_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
