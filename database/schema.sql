-- ============================================
-- Ironcrest Email Signature Generator
-- Database Schema v1.0
-- ============================================

-- USERS TABLE
CREATE TABLE IF NOT EXISTS sig_users (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  email         VARCHAR(255) NOT NULL,
  email_norm    VARCHAR(255) NOT NULL COMMENT 'Normalized lowercase email',
  is_verified   TINYINT(1) NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_sig_users_email_norm (email_norm),
  KEY ix_sig_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SIGNATURES TABLE
CREATE TABLE IF NOT EXISTS sig_signatures (
  id              BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  public_uuid     CHAR(36) NOT NULL COMMENT 'Public shareable UUID',
  template_key    VARCHAR(64) NOT NULL,
  title           VARCHAR(255) DEFAULT NULL COMMENT 'User-given name',
  config_json     JSON NOT NULL COMMENT 'Full signature configuration',
  preview_url     VARCHAR(512) DEFAULT NULL COMMENT 'Preview image URL',
  is_deleted      TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sig_signatures_user FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE,
  UNIQUE KEY ux_sig_signatures_uuid (public_uuid),
  KEY ix_sig_signatures_user (user_id, created_at),
  KEY ix_sig_signatures_template (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TEMPLATES TABLE (Catalog)
CREATE TABLE IF NOT EXISTS sig_templates (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  template_key  VARCHAR(64) NOT NULL,
  name          VARCHAR(128) NOT NULL,
  version       INT NOT NULL DEFAULT 1,
  description   TEXT DEFAULT NULL,
  thumbnail_url VARCHAR(512) DEFAULT NULL,
  meta_json     JSON NOT NULL COMMENT 'Template metadata (atoms, layout, etc)',
  default_json  JSON NOT NULL COMMENT 'Default configuration',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_sig_templates_key_version (template_key, version),
  KEY ix_sig_templates_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EVENTS TABLE (Analytics + Rate Limiting)
CREATE TABLE IF NOT EXISTS sig_events (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NULL,
  signature_id  BIGINT UNSIGNED NULL,
  event_type    VARCHAR(64) NOT NULL COMMENT 'edit, render, copy_html, download, etc',
  ip_hash       CHAR(64) DEFAULT NULL COMMENT 'SHA256 of IP + salt',
  user_agent    VARCHAR(255) DEFAULT NULL,
  meta_json     JSON NULL COMMENT 'Additional event data',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_sig_events_type_time (event_type, created_at),
  KEY ix_sig_events_user_time (user_id, created_at),
  KEY ix_sig_events_ip_time (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- MAGIC LINKS TABLE (Passwordless Auth)
CREATE TABLE IF NOT EXISTS sig_magic_links (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  token         CHAR(64) NOT NULL COMMENT 'Secure random token',
  redirect_path VARCHAR(255) DEFAULT NULL,
  expires_at    DATETIME NOT NULL,
  used_at       DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_sig_magic_links_token (token),
  KEY ix_sig_magic_links_expires (expires_at),
  CONSTRAINT fk_sig_magic_links_user FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- EMAIL VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS sig_email_verifications (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  token         CHAR(64) NOT NULL,
  expires_at    DATETIME NOT NULL,
  verified_at   DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_sig_email_verifications_token (token),
  KEY ix_sig_email_verifications_expires (expires_at),
  CONSTRAINT fk_sig_email_verifications_user FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PHASE 2 TABLES (Future - Teams/Paid Plans)
-- ============================================

-- ORGANIZATIONS TABLE
CREATE TABLE IF NOT EXISTS sig_organizations (
  id         BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  name       VARCHAR(255) NOT NULL,
  plan       ENUM('free','pro','business') NOT NULL DEFAULT 'free',
  settings_json JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORG MEMBERS TABLE
CREATE TABLE IF NOT EXISTS sig_org_members (
  id       BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  org_id   BIGINT UNSIGNED NOT NULL,
  user_id  BIGINT UNSIGNED NOT NULL,
  role     ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY ux_sig_org_members (org_id, user_id),
  CONSTRAINT fk_sig_org_members_org FOREIGN KEY (org_id) REFERENCES sig_organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_sig_org_members_user FOREIGN KEY (user_id) REFERENCES sig_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ORG TEMPLATES TABLE (Locked Brand Templates)
CREATE TABLE IF NOT EXISTS sig_org_templates (
  id            BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  org_id        BIGINT UNSIGNED NOT NULL,
  template_key  VARCHAR(64) NOT NULL,
  version       INT NOT NULL DEFAULT 1,
  locked_fields JSON NOT NULL COMMENT 'Fields that members cannot change',
  created_by    BIGINT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_sig_org_templates_org FOREIGN KEY (org_id) REFERENCES sig_organizations(id) ON DELETE CASCADE,
  CONSTRAINT fk_sig_org_templates_user FOREIGN KEY (created_by) REFERENCES sig_users(id),
  KEY ix_sig_org_templates_org (org_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- SUBSCRIPTIONS TABLE (Stripe Integration)
CREATE TABLE IF NOT EXISTS sig_subscriptions (
  id                 BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  org_id             BIGINT UNSIGNED NOT NULL,
  provider           ENUM('stripe') NOT NULL DEFAULT 'stripe',
  customer_id        VARCHAR(128) NOT NULL,
  subscription_id    VARCHAR(128) NOT NULL,
  status             ENUM('active','past_due','canceled','trialing') NOT NULL,
  current_period_end DATETIME NOT NULL,
  created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY ux_sig_subs_subscription (subscription_id),
  CONSTRAINT fk_sig_subs_org FOREIGN KEY (org_id) REFERENCES sig_organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INDEXES FOR PERFORMANCE
-- ============================================

-- Cleanup old events (run periodically)
-- DELETE FROM sig_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Cleanup expired magic links (run periodically)
-- DELETE FROM sig_magic_links WHERE expires_at < NOW() AND used_at IS NULL;

-- ============================================
-- INITIAL DATA
-- ============================================

-- Insert system user for orphaned signatures
INSERT INTO sig_users (id, email, email_norm, is_verified) 
VALUES (1, 'system@ironcrestsoftware.com', 'system@ironcrestsoftware.com', 1)
ON DUPLICATE KEY UPDATE email = email;

-- ============================================
-- SCHEMA VERSION
-- ============================================
CREATE TABLE IF NOT EXISTS sig_schema_version (
  version INT NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO sig_schema_version (version) VALUES (1);
