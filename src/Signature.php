<?php
namespace Ironcrest\Signature;

/**
 * Signature Model
 */
class Signature {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new signature
     */
    public function create($userId, $templateKey, $config, $title = null) {
        $uuid = $this->generateUuid();
        
        $data = [
            'user_id' => $userId,
            'public_uuid' => $uuid,
            'template_key' => $templateKey,
            'title' => $title,
            'config_json' => json_encode($config),
        ];
        
        $id = $this->db->insert('sig_signatures', $data);
        
        return [
            'id' => $id,
            'uuid' => $uuid,
        ];
    }
    
    /**
     * Get signature by ID
     */
    public function getById($id, $userId = null) {
        $sql = 'SELECT * FROM sig_signatures WHERE id = ? AND is_deleted = 0';
        $params = [$id];
        
        if ($userId !== null) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        
        $signature = $this->db->fetchOne($sql, $params);
        
        if ($signature) {
            $signature['config_json'] = json_decode($signature['config_json'], true);
        }
        
        return $signature;
    }
    
    /**
     * Get signature by UUID (public access)
     */
    public function getByUuid($uuid) {
        $sql = 'SELECT * FROM sig_signatures WHERE public_uuid = ? AND is_deleted = 0';
        $signature = $this->db->fetchOne($sql, [$uuid]);
        
        if ($signature) {
            $signature['config_json'] = json_decode($signature['config_json'], true);
        }
        
        return $signature;
    }
    
    /**
     * Get all signatures for user
     */
    public function getByUser($userId, $limit = 50, $offset = 0) {
        $sql = 'SELECT * FROM sig_signatures 
                WHERE user_id = ? AND is_deleted = 0 
                ORDER BY updated_at DESC 
                LIMIT ? OFFSET ?';
        
        $signatures = $this->db->fetchAll($sql, [$userId, $limit, $offset]);
        
        foreach ($signatures as &$sig) {
            $sig['config_json'] = json_decode($sig['config_json'], true);
        }
        
        return $signatures;
    }
    
    /**
     * Update signature
     */
    public function update($id, $userId, $data) {
        $allowed = ['template_key', 'title', 'config_json'];
        $updateData = [];
        
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                if ($field === 'config_json' && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (empty($updateData)) {
            return false;
        }
        
        return $this->db->update(
            'sig_signatures',
            $updateData,
            'id = ? AND user_id = ?',
            [$id, $userId]
        );
    }
    
    /**
     * Soft delete signature
     */
    public function delete($id, $userId) {
        return $this->db->update(
            'sig_signatures',
            ['is_deleted' => 1],
            'id = ? AND user_id = ?',
            [$id, $userId]
        );
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid() {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
