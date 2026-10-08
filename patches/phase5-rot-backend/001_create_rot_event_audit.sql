-- Assam Motors ERP
-- Phase 5: ROT event/audit history
-- Safe additive migration: creates a new independent table only.

CREATE TABLE IF NOT EXISTS rot_event_audit (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_key VARCHAR(128) NULL,
    rot_session_id VARCHAR(64) NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    job_card_id BIGINT UNSIGNED NULL,
    job_card_no VARCHAR(80) NULL,
    rot_code VARCHAR(80) NULL,

    event_type VARCHAR(24) NOT NULL,
    status_before VARCHAR(40) NULL,
    status_after VARCHAR(40) NULL,

    pause_reason VARCHAR(120) NULL,
    pause_note TEXT NULL,

    event_at_local DATETIME(3) NOT NULL,
    event_at_utc DATETIME(3) NOT NULL,
    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',

    device_recorded_at_ms BIGINT NULL,
    action_source VARCHAR(64) NOT NULL DEFAULT 'ERP',
    payload_json LONGTEXT NULL,

    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_rot_event_request_key (request_key),
    KEY idx_rot_event_session_time (rot_session_id, event_at_utc),
    KEY idx_rot_event_staff_time (staff_id, event_at_utc),
    KEY idx_rot_event_job_time (job_card_id, event_at_utc),
    KEY idx_rot_event_job_no_time (job_card_no, event_at_utc),
    KEY idx_rot_event_type_time (event_type, event_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
