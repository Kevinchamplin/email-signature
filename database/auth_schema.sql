-- Authentication System Schema
-- Run this to add authentication tables to existing database

-- Update sig_users table to add authentication fields
ALTER TABLE sig_users
ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER email_norm,
ADD COLUMN is_registered TINYINT(1) DEFAULT 0 AFTER password_hash,
ADD COLUMN is_grandfathered TINYINT(1) DEFAULT 1 COMMENT 'Early users get free forever' AFTER is_registered,
ADD COLUMN account_tier ENUM('free', 'basic', 'pro', 'enterprise') DEFAULT 'free' AFTER is_grandfathered,
ADD COLUMN email_verified TINYINT(1) DEFAULT 0 AFTER account_tier,
ADD COLUMN last_login_at TIMESTAMP NULL DEFAULT NULL AFTER email_verified,
ADD COLUMN login_count INT DEFAULT 0 AFTER last_login_at;

-- Sessions table for managing user sessions
CREATE TABLE IF NOT EXISTS sig_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS sig_password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Feature access control
CREATE TABLE IF NOT EXISTS sig_feature_access (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    feature_key VARCHAR(50) NOT NULL UNIQUE,
    feature_name VARCHAR(100) NOT NULL,
    description TEXT,
    free_tier TINYINT(1) DEFAULT 0,
    basic_tier TINYINT(1) DEFAULT 0,
    pro_tier TINYINT(1) DEFAULT 1,
    enterprise_tier TINYINT(1) DEFAULT 1,
    grandfathered_access TINYINT(1) DEFAULT 1 COMMENT 'Early users get all features',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default features
INSERT INTO sig_feature_access (feature_key, feature_name, description, free_tier, basic_tier, pro_tier, enterprise_tier, grandfathered_access) VALUES
('basic_templates', 'Basic Templates', 'Access to 3 basic signature templates', 1, 1, 1, 1, 1),
('premium_templates', 'Premium Templates', 'Access to all 10+ premium templates', 0, 1, 1, 1, 1),
('save_signatures', 'Save Signatures', 'Save and manage multiple signatures', 0, 1, 1, 1, 1),
('custom_branding', 'Custom Branding', 'Custom colors and branding', 0, 1, 1, 1, 1),
('analytics', 'Analytics', 'Track signature performance', 0, 0, 1, 1, 1),
('team_management', 'Team Management', 'Manage team signatures', 0, 0, 0, 1, 1),
('api_access', 'API Access', 'Programmatic signature generation', 0, 0, 0, 1, 1),
('priority_support', 'Priority Support', 'Priority email support', 0, 0, 1, 1, 1);

-- Update existing users to be grandfathered
UPDATE sig_users SET is_grandfathered = 1 WHERE created_at < NOW();
