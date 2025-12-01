-- User Activity Tracking
-- Track user actions and engagement

CREATE TABLE IF NOT EXISTS sig_user_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    signature_id BIGINT UNSIGNED NULL,
    activity_type ENUM(
        'copy_html',
        'download_html',
        'email_signature',
        'create_signature',
        'edit_signature',
        'delete_signature',
        'view_analytics',
        'export_signature',
        'save_profile',
        'login',
        'register',
        'template_change',
        'preview_signature'
    ) NOT NULL,
    activity_data JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_signature (signature_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE,
    FOREIGN KEY (signature_id) REFERENCES sig_signatures(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest activity tracking (anonymous users)
CREATE TABLE IF NOT EXISTS sig_guest_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guest_id VARCHAR(64) NOT NULL,
    session_id VARCHAR(64),
    activity_type ENUM(
        'copy_html',
        'download_html',
        'email_signature',
        'create_signature',
        'preview_signature',
        'template_change',
        'visit_page'
    ) NOT NULL,
    activity_data JSON NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_guest (guest_id),
    INDEX idx_session (session_id),
    INDEX idx_ip (ip_address),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guest activity summary (for abuse detection)
CREATE TABLE IF NOT EXISTS sig_guest_activity_summary (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    guest_id VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    total_activities INT DEFAULT 0,
    total_signatures_created INT DEFAULT 0,
    total_copy_html INT DEFAULT 0,
    total_downloads INT DEFAULT 0,
    is_flagged BOOLEAN DEFAULT FALSE,
    flag_reason VARCHAR(255),
    first_seen_at DATETIME,
    last_seen_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip (ip_address),
    INDEX idx_flagged (is_flagged),
    INDEX idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity summary by user (for quick stats)
CREATE TABLE IF NOT EXISTS sig_user_activity_summary (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    total_signatures_created INT DEFAULT 0,
    total_copy_html INT DEFAULT 0,
    total_downloads INT DEFAULT 0,
    total_emails_sent INT DEFAULT 0,
    total_logins INT DEFAULT 0,
    last_activity_at DATETIME,
    first_activity_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_last_activity (last_activity_at),
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
