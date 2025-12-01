-- Analytics System for Email Signature Generator
-- Tracks views, clicks, and engagement metrics

-- Signature Views (tracking pixel)
CREATE TABLE IF NOT EXISTS sig_analytics_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signature_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    email_client VARCHAR(100),
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
    country VARCHAR(2),
    city VARCHAR(100),
    INDEX idx_signature (signature_id),
    INDEX idx_user (user_id),
    INDEX idx_date (viewed_at),
    FOREIGN KEY (signature_id) REFERENCES sig_signatures(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Link Clicks
CREATE TABLE IF NOT EXISTS sig_analytics_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signature_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    link_type ENUM('email', 'phone', 'website', 'linkedin', 'twitter', 'github', 'calendly', 'custom') NOT NULL,
    link_url TEXT NOT NULL,
    clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type ENUM('desktop', 'mobile', 'tablet', 'unknown') DEFAULT 'unknown',
    country VARCHAR(2),
    city VARCHAR(100),
    referrer TEXT,
    INDEX idx_signature (signature_id),
    INDEX idx_user (user_id),
    INDEX idx_link_type (link_type),
    INDEX idx_date (clicked_at),
    FOREIGN KEY (signature_id) REFERENCES sig_signatures(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily aggregated stats (for faster queries)
CREATE TABLE IF NOT EXISTS sig_analytics_daily (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signature_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    total_views INT DEFAULT 0,
    total_clicks INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    email_clicks INT DEFAULT 0,
    phone_clicks INT DEFAULT 0,
    website_clicks INT DEFAULT 0,
    linkedin_clicks INT DEFAULT 0,
    twitter_clicks INT DEFAULT 0,
    github_clicks INT DEFAULT 0,
    calendly_clicks INT DEFAULT 0,
    custom_clicks INT DEFAULT 0,
    desktop_views INT DEFAULT 0,
    mobile_views INT DEFAULT 0,
    tablet_views INT DEFAULT 0,
    UNIQUE KEY unique_signature_date (signature_id, date),
    INDEX idx_user (user_id),
    INDEX idx_date (date),
    FOREIGN KEY (signature_id) REFERENCES sig_signatures(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tracking links (shortened URLs)
CREATE TABLE IF NOT EXISTS sig_tracking_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    signature_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    short_code VARCHAR(20) UNIQUE NOT NULL,
    link_type ENUM('email', 'phone', 'website', 'linkedin', 'twitter', 'github', 'calendly', 'custom') NOT NULL,
    destination_url TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    INDEX idx_short_code (short_code),
    INDEX idx_signature (signature_id),
    FOREIGN KEY (signature_id) REFERENCES sig_signatures(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Analytics settings per user
CREATE TABLE IF NOT EXISTS sig_analytics_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    tracking_enabled BOOLEAN DEFAULT TRUE,
    track_views BOOLEAN DEFAULT TRUE,
    track_clicks BOOLEAN DEFAULT TRUE,
    track_location BOOLEAN DEFAULT TRUE,
    track_device BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
