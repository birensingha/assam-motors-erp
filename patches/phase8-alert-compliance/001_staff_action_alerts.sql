-- Assam Motors ERP
-- Phase 8: server-authoritative Staff action alerts.

CREATE TABLE IF NOT EXISTS staff_action_alerts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    identity_key CHAR(64) NOT NULL,

    staff_id BIGINT UNSIGNED NOT NULL,
    alert_type VARCHAR(64) NOT NULL,
    target_type VARCHAR(48) NOT NULL,
    target_key VARCHAR(190) NOT NULL,

    job_card_id BIGINT UNSIGNED NULL,
    job_card_no VARCHAR(80) NULL,
    rot_session_id VARCHAR(64) NULL,
    vehicle_reg_no VARCHAR(40) NULL,

    title VARCHAR(190) NOT NULL,
    message TEXT NULL,

    status VARCHAR(32) NOT NULL DEFAULT 'ACTIVE',
    postpone_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_postponements TINYINT UNSIGNED NOT NULL DEFAULT 3,

    first_triggered_at_local DATETIME(3) NOT NULL,
    first_triggered_at_utc DATETIME(3) NOT NULL,
    next_reminder_at_local DATETIME(3) NULL,
    next_reminder_at_utc DATETIME(3) NULL,
    last_notified_at_local DATETIME(3) NULL,
    last_notified_at_utc DATETIME(3) NULL,

    escalated_at_local DATETIME(3) NULL,
    escalated_at_utc DATETIME(3) NULL,
    admin_reviewed_by BIGINT UNSIGNED NULL,
    admin_reviewed_at_local DATETIME(3) NULL,
    admin_reviewed_at_utc DATETIME(3) NULL,
    admin_note TEXT NULL,

    resolved_at_local DATETIME(3) NULL,
    resolved_at_utc DATETIME(3) NULL,
    resolved_by VARCHAR(80) NULL,
    resolution_note TEXT NULL,

    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_alert_identity (identity_key),
    KEY idx_staff_alert_staff_status (staff_id, status, updated_at),
    KEY idx_staff_alert_status_time (status, updated_at),
    KEY idx_staff_alert_job (job_card_id),
    KEY idx_staff_alert_rot (rot_session_id),
    KEY idx_staff_alert_next (next_reminder_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_action_alert_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alert_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(48) NOT NULL,

    postpone_count TINYINT UNSIGNED NULL,
    status_before VARCHAR(32) NULL,
    status_after VARCHAR(32) NULL,
    actor_type VARCHAR(32) NOT NULL DEFAULT 'SYSTEM',
    actor_id VARCHAR(80) NULL,
    note TEXT NULL,
    payload_json LONGTEXT NULL,

    event_at_local DATETIME(3) NOT NULL,
    event_at_utc DATETIME(3) NOT NULL,
    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_staff_alert_event_alert_time (alert_id, event_at_utc),
    KEY idx_staff_alert_event_staff_time (staff_id, event_at_utc),
    KEY idx_staff_alert_event_type_time (event_type, event_at_utc)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
