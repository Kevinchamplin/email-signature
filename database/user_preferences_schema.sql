-- User Preferences Schema
-- Stores user's signature information for easy template switching

-- User Preferences Table
CREATE TABLE IF NOT EXISTS sig_user_preferences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    
    -- Identity
    name VARCHAR(255) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    pronouns VARCHAR(50) DEFAULT NULL,
    
    -- Company
    company_name VARCHAR(255) DEFAULT NULL,
    logo_url TEXT DEFAULT NULL,
    
    -- Contact
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    calendly VARCHAR(255) DEFAULT NULL,
    
    -- Social Links (JSON)
    social_links JSON DEFAULT NULL,
    -- Example: {"linkedin": "https://...", "x": "https://...", "github": "https://..."}
    
    -- Branding (JSON)
    branding_preferences JSON DEFAULT NULL,
    -- Example: {"accent": "#2B68C1", "fontSize": "medium", "logoSize": 80, ...}
    
    -- Add-ons (JSON)
    addons JSON DEFAULT NULL,
    -- Example: {"cta": {"label": "Book Call", "url": "https://..."}, "disclaimer": "..."}
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Signature History Table
-- Tracks which templates users have created
CREATE TABLE IF NOT EXISTS sig_user_signature_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    template_key VARCHAR(50) NOT NULL,
    config_json JSON NOT NULL,
    signature_html MEDIUMTEXT NOT NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    use_count INT UNSIGNED DEFAULT 1,
    
    -- Indexes
    INDEX idx_user_template (user_id, template_key),
    INDEX idx_user_recent (user_id, last_used_at),
    FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default preferences for existing users
-- (Run this after creating the table)
INSERT INTO sig_user_preferences (user_id, name, email)
SELECT id, COALESCE(email, CONCAT('user', id, '@example.com')), COALESCE(email, CONCAT('user', id, '@example.com'))
FROM sig_users
WHERE id NOT IN (SELECT user_id FROM sig_user_preferences)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
