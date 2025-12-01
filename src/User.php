<?php
namespace Ironcrest\Signature;

/**
 * User Model
 */
class User {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create or get existing user by email
     */
    public function createOrGet($email) {
        $emailNorm = strtolower(trim($email));
        
        // Check if user exists
        $existing = $this->getByEmail($emailNorm);
        if ($existing) {
            return $existing;
        }
        
        // Create new user
        $data = [
            'email' => $email,
            'email_norm' => $emailNorm,
            'is_verified' => 0,
        ];
        
        $id = $this->db->insert('sig_users', $data);
        
        return $this->getById($id);
    }
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        return $this->db->fetchOne(
            'SELECT * FROM sig_users WHERE id = ?',
            [$id]
        );
    }
    
    /**
     * Get user by email
     */
    public function getByEmail($email) {
        $emailNorm = strtolower(trim($email));
        return $this->db->fetchOne(
            'SELECT * FROM sig_users WHERE email_norm = ?',
            [$emailNorm]
        );
    }
    
    /**
     * Mark email as verified
     */
    public function markVerified($userId) {
        return $this->db->update(
            'sig_users',
            ['is_verified' => 1],
            'id = ?',
            [$userId]
        );
    }
    
    /**
     * Create magic link token
     */
    public function createMagicLink($userId, $redirectPath = null, $expirySeconds = 3600) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $expirySeconds);
        
        $data = [
            'user_id' => $userId,
            'token' => $token,
            'redirect_path' => $redirectPath,
            'expires_at' => $expiresAt,
        ];
        
        $this->db->insert('sig_magic_links', $data);
        
        return $token;
    }
    
    /**
     * Verify magic link token
     */
    public function verifyMagicLink($token) {
        $sql = 'SELECT * FROM sig_magic_links 
                WHERE token = ? 
                AND expires_at > NOW() 
                AND used_at IS NULL';
        
        $link = $this->db->fetchOne($sql, [$token]);
        
        if (!$link) {
            return null;
        }
        
        // Mark as used
        $this->db->update(
            'sig_magic_links',
            ['used_at' => date('Y-m-d H:i:s')],
            'token = ?',
            [$token]
        );
        
        // Get user
        $user = $this->getById($link['user_id']);
        
        return [
            'user' => $user,
            'redirect_path' => $link['redirect_path'],
        ];
    }
    
    /**
     * Clean up expired magic links
     */
    public function cleanupExpiredLinks() {
        $sql = 'DELETE FROM sig_magic_links WHERE expires_at < NOW()';
        return $this->db->query($sql);
    }
}
