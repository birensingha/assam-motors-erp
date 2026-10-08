-- Assam Motors ERP
-- Phase 10: Staff FCM device registry and push delivery audit.

CREATE TABLE IF NOT EXISTS staff_push_devices (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    staff_id BIGINT UNSIGNED NOT NULL,
    installation_id VARCHAR(80) NOT NULL,

    fcm_token TEXT NOT NULL,
    token_hash CHAR(64) NOT NULL,

    platform VARCHAR(24) NOT NULL DEFAULT 'ANDROID',
    app_version VARCHAR(40) NULL,
    app_build INT NULL,

    active TINYINT(1) NOT NULL DEFAULT 1,
    registered_at_local DATETIME(3) NOT NULL,
    registered_at_utc DATETIME(3) NOT NULL,
    last_seen_at_local DATETIME(3) NOT NULL,
    last_seen_at_utc DATETIME(3) NOT NULL,
    disabled_at_local DATETIME(3) NULL,
    disabled_at_utc DATETIME(3) NULL,
    disable_reason VARCHAR(190) NULL,

    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_push_token_hash (token_hash),
    KEY idx_staff_push_staff_active (staff_id, active, updated_at),
    KEY idx_staff_push_installation (installation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS staff_push_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    alert_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NOT NULL,

    push_id VARCHAR(190) NOT NULL,
    delivery_status VARCHAR(32) NOT NULL,
    fcm_message_name VARCHAR(255) NULL,
    error_code VARCHAR(80) NULL,
    error_message VARCHAR(500) NULL,

    sent_at_local DATETIME(3) NOT NULL,
    sent_at_utc DATETIME(3) NOT NULL,
    timezone_name VARCHAR(64) NOT NULL DEFAULT 'Asia/Kolkata',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_push_delivery_alert_time (alert_id, sent_at_utc),
    KEY idx_push_delivery_device_time (device_id, sent_at_utc),
    KEY idx_push_delivery_staff_time (staff_id, sent_at_utc),
    KEY idx_push_delivery_push_id (push_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
