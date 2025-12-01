<?php
/**
 * Authentication Class
 * Handles user registration, login, sessions, and feature access
 */

namespace Ironcrest\Signature;

class Auth {
    private $db;
    private $config;
    
    public function __construct(Database $db, array $config = []) {
        $this->db = $db;
        $this->config = $config;
    }
    
    /**
     * Register new user
     */
    public function register($email, $password, $name = null) {
        // Validate email
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }
        
        // Check if user already exists
        $existing = $this->db->fetchOne(
            'SELECT id, is_registered FROM sig_users WHERE email_norm = ?',
            [strtolower($email)]
        );
        
        if ($existing && $existing['is_registered']) {
            return ['success' => false, 'error' => 'Email already registered'];
        }
        
        // Validate password
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        
        if ($existing) {
            // Update existing user to registered
            $this->db->update('sig_users', [
                'password_hash' => $passwordHash,
                'is_registered' => 1,
                'is_grandfathered' => 1, // All early users are grandfathered
                'email_verified' => 0,
            ], 'id = ?', [$existing['id']]);
            
            $userId = $existing['id'];
        } else {
            // Create new user
            $userId = $this->db->insert('sig_users', [
                'email' => $email,
                'email_norm' => strtolower($email),
                'password_hash' => $passwordHash,
                'is_registered' => 1,
                'is_grandfathered' => 1, // All early users are grandfathered
                'email_verified' => 0,
            ]);
        }
        
        // Create session
        $session = $this->createSession($userId);
        
        // Check if beta is active (for grandfathered status)
        $betaConfig = require __DIR__ . '/../config/beta.php';
        $isGrandfathered = $betaConfig['AUTO_GRANDFATHER'] ?? false;
        
        return [
            'success' => true,
            'user_id' => $userId,
            'session_token' => $session['token'],
            'is_grandfathered' => $isGrandfathered,
            'beta_active' => $betaConfig['BETA_ACTIVE'] ?? false,
        ];
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        // Validate email
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }
        
        // Get user
        $user = $this->db->fetchOne(
            'SELECT id, password_hash, is_registered, is_grandfathered, account_tier FROM sig_users WHERE email_norm = ?',
            [strtolower($email)]
        );
        
        if (!$user || !$user['is_registered']) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Invalid email or password'];
        }
        
        // Update login stats
        $this->db->update('sig_users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'login_count' => Database::raw('login_count + 1'),
        ], 'id = ?', [$user['id']]);
        
        // Create session
        $session = $this->createSession($user['id']);
        
        return [
            'success' => true,
            'user_id' => $user['id'],
            'session_token' => $session['token'],
            'is_grandfathered' => (bool)$user['is_grandfathered'],
            'account_tier' => $user['account_tier'],
        ];
    }
    
    /**
     * Create session
     */
    private function createSession($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $this->db->insert('sig_sessions', [
            'user_id' => $userId,
            'session_token' => $token,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'expires_at' => $expiresAt,
        ]);
        
        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }
    
    /**
     * Validate session
     */
    public function validateSession($token) {
        if (!$token) {
            return null;
        }
        
        // First try unified auth sessions (ironcrest_sessions)
        $session = null;
        try {
            $session = $this->db->fetchOne(
                'SELECT s.*, u.email, u.is_grandfathered, u.account_tier 
                 FROM ironcrest_sessions s 
                 JOIN ironcrest_users u ON s.user_id = u.id 
                 WHERE s.session_token = ? AND s.expires_at > NOW()',
                [$token]
            );
        } catch (\Exception $e) {
            error_log('Unified auth check failed: ' . $e->getMessage());
        }
        
        // Fallback to legacy email-signature sessions
        if (!$session) {
            try {
                $session = $this->db->fetchOne(
                    'SELECT s.*, u.email, u.is_grandfathered, u.account_tier 
                     FROM sig_sessions s 
                     JOIN sig_users u ON s.user_id = u.id 
                     WHERE s.session_token = ? AND s.expires_at > NOW()',
                    [$token]
                );
            } catch (\Exception $e) {
                error_log('Legacy auth check failed: ' . $e->getMessage());
                return null;
            }
        }
        
        if (!$session) {
            return null;
        }
        
        // Update session activity in both tables
        try {
            $this->db->update('ironcrest_sessions', [
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'session_token = ?', [$token]);
        } catch (\Exception $e) {
            // Ignore if table doesn't have this session
        }
        
        try {
            $this->db->update('sig_sessions', [
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'session_token = ?', [$token]);
        } catch (\Exception $e) {
            // Ignore if table doesn't have this session
        }
        
        return [
            'user_id' => $session['user_id'],
            'email' => $session['email'],
            'is_grandfathered' => (bool)$session['is_grandfathered'],
            'account_tier' => $session['account_tier'],
        ];
    }
    
    /**
     * Logout (invalidate session)
     */
    public function logout($token) {
        $this->db->delete('sig_sessions', 'session_token = ?', [$token]);
        return ['success' => true];
    }
    
    /**
     * Check if user has access to feature
     */
    public function hasFeatureAccess($userId, $featureKey) {
        // Get user info
        $user = $this->db->fetchOne(
            'SELECT is_grandfathered, account_tier FROM sig_users WHERE id = ?',
            [$userId]
        );
        
        if (!$user) {
            return false;
        }
        
        // Grandfathered users get everything
        if ($user['is_grandfathered']) {
            return true;
        }
        
        // Check feature access
        $feature = $this->db->fetchOne(
            'SELECT * FROM sig_feature_access WHERE feature_key = ?',
            [$featureKey]
        );
        
        if (!$feature) {
            return false;
        }
        
        // Check tier access
        $tierColumn = $user['account_tier'] . '_tier';
        return (bool)$feature[$tierColumn];
    }
    
    /**
     * Get user's accessible features
     */
    public function getUserFeatures($userId) {
        $user = $this->db->fetchOne(
            'SELECT is_grandfathered, account_tier FROM sig_users WHERE id = ?',
            [$userId]
        );
        
        if (!$user) {
            return [];
        }
        
        // Grandfathered users get all features
        if ($user['is_grandfathered']) {
            $features = $this->db->fetchAll(
                'SELECT feature_key, feature_name, description FROM sig_feature_access'
            );
        } else {
            // Get features for user's tier
            $tierColumn = $user['account_tier'] . '_tier';
            $features = $this->db->fetchAll(
                "SELECT feature_key, feature_name, description FROM sig_feature_access WHERE $tierColumn = 1"
            );
        }
        
        return $features;
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        $email = filter_var($email, FILTER_VALIDATE_EMAIL);
        if (!$email) {
            return ['success' => false, 'error' => 'Invalid email address'];
        }
        
        $user = $this->db->fetchOne(
            'SELECT id FROM sig_users WHERE email_norm = ? AND is_registered = 1',
            [strtolower($email)]
        );
        
        if (!$user) {
            // Don't reveal if email exists
            return ['success' => true, 'message' => 'If email exists, reset link sent'];
        }
        
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->db->insert('sig_password_resets', [
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expiresAt,
        ]);
        
        // TODO: Send email with reset link
        
        return [
            'success' => true,
            'message' => 'Password reset link sent',
            'token' => $token, // Remove in production
        ];
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword) {
        // Validate token
        $reset = $this->db->fetchOne(
            'SELECT * FROM sig_password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL',
            [$token]
        );
        
        if (!$reset) {
            return ['success' => false, 'error' => 'Invalid or expired reset token'];
        }
        
        // Validate password
        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }
        
        // Hash password
        $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
        
        // Update password
        $this->db->update('sig_users', [
            'password_hash' => $passwordHash,
        ], 'id = ?', [$reset['user_id']]);
        
        // Mark token as used
        $this->db->update('sig_password_resets', [
            'used_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$reset['id']]);
        
        // Invalidate all sessions
        $this->db->delete('sig_sessions', 'user_id = ?', [$reset['user_id']]);
        
        return ['success' => true, 'message' => 'Password reset successfully'];
    }
}
