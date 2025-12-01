<?php
namespace Ironcrest\Signature;

/**
 * User Activity Tracking
 * Track user actions and engagement
 */
class UserActivity {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Log a user activity
     */
    public function log($userId, $activityType, $signatureId = null, $activityData = null) {
        try {
            $this->db->insert('sig_user_activities', [
                'user_id' => $userId,
                'signature_id' => $signatureId,
                'activity_type' => $activityType,
                'activity_data' => $activityData ? json_encode($activityData) : null,
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update summary
            $this->updateSummary($userId, $activityType);
            
            return true;
        } catch (\Exception $e) {
            error_log("User activity logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update user activity summary
     */
    private function updateSummary($userId, $activityType) {
        // Check if summary exists
        $summary = $this->db->fetchOne(
            'SELECT * FROM sig_user_activity_summary WHERE user_id = ?',
            [$userId]
        );
        
        if ($summary) {
            // Update existing summary
            $updates = [
                'last_activity_at' => date('Y-m-d H:i:s')
            ];
            
            switch ($activityType) {
                case 'create_signature':
                    $updates['total_signatures_created'] = Database::raw('total_signatures_created + 1');
                    break;
                case 'copy_html':
                    $updates['total_copy_html'] = Database::raw('total_copy_html + 1');
                    break;
                case 'download_html':
                    $updates['total_downloads'] = Database::raw('total_downloads + 1');
                    break;
                case 'email_signature':
                    $updates['total_emails_sent'] = Database::raw('total_emails_sent + 1');
                    break;
                case 'login':
                    $updates['total_logins'] = Database::raw('total_logins + 1');
                    break;
            }
            
            $this->db->update('sig_user_activity_summary', $updates, 'user_id = ?', [$userId]);
        } else {
            // Create new summary
            $data = [
                'user_id' => $userId,
                'total_signatures_created' => 0,
                'total_copy_html' => 0,
                'total_downloads' => 0,
                'total_emails_sent' => 0,
                'total_logins' => 0,
                'last_activity_at' => date('Y-m-d H:i:s'),
                'first_activity_at' => date('Y-m-d H:i:s')
            ];
            
            // Increment the appropriate counter
            switch ($activityType) {
                case 'create_signature':
                    $data['total_signatures_created'] = 1;
                    break;
                case 'copy_html':
                    $data['total_copy_html'] = 1;
                    break;
                case 'download_html':
                    $data['total_downloads'] = 1;
                    break;
                case 'email_signature':
                    $data['total_emails_sent'] = 1;
                    break;
                case 'login':
                    $data['total_logins'] = 1;
                    break;
            }
            
            $this->db->insert('sig_user_activity_summary', $data);
        }
    }
    
    /**
     * Get user activity history
     */
    public function getUserActivity($userId, $limit = 50, $activityType = null) {
        $sql = 'SELECT * FROM sig_user_activities WHERE user_id = ?';
        $params = [$userId];
        
        if ($activityType) {
            $sql .= ' AND activity_type = ?';
            $params[] = $activityType;
        }
        
        $sql .= ' ORDER BY created_at DESC LIMIT ?';
        $params[] = $limit;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get user activity summary
     */
    public function getUserSummary($userId) {
        return $this->db->fetchOne(
            'SELECT * FROM sig_user_activity_summary WHERE user_id = ?',
            [$userId]
        );
    }
    
    /**
     * Get all users activity stats (admin view)
     */
    public function getAllUsersStats($limit = 100, $orderBy = 'last_activity_at') {
        $allowedOrderBy = ['last_activity_at', 'total_signatures_created', 'total_copy_html', 'total_downloads', 'total_emails_sent'];
        
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'last_activity_at';
        }
        
        return $this->db->fetchAll("
            SELECT 
                s.*,
                u.email,
                u.is_grandfathered,
                u.account_tier,
                u.created_at as user_created_at
            FROM sig_user_activity_summary s
            JOIN sig_users u ON s.user_id = u.id
            ORDER BY s.{$orderBy} DESC
            LIMIT ?
        ", [$limit]);
    }
    
    /**
     * Get activity breakdown by type
     */
    public function getActivityBreakdown($userId = null, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        $sql = "
            SELECT 
                activity_type,
                COUNT(*) as count,
                DATE(created_at) as date
            FROM sig_user_activities
            WHERE created_at >= ?
        ";
        
        $params = [$startDate];
        
        if ($userId) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }
        
        $sql .= " GROUP BY activity_type, DATE(created_at) ORDER BY date DESC, count DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get most active users
     */
    public function getMostActiveUsers($limit = 10, $days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        return $this->db->fetchAll("
            SELECT 
                u.id,
                u.email,
                COUNT(a.id) as activity_count,
                MAX(a.created_at) as last_activity
            FROM sig_users u
            JOIN sig_user_activities a ON u.id = a.user_id
            WHERE a.created_at >= ?
            GROUP BY u.id, u.email
            ORDER BY activity_count DESC
            LIMIT ?
        ", [$startDate, $limit]);
    }
    
    /**
     * Log guest activity (anonymous users)
     */
    public function logGuest($guestId, $activityType, $activityData = null) {
        try {
            $ip = $this->getClientIP();
            
            // Check for abuse before logging
            if ($this->isAbusive($guestId, $ip, $activityType)) {
                error_log("Abusive activity detected - Guest: {$guestId}, IP: {$ip}, Type: {$activityType}");
                return false;
            }
            
            $this->db->insert('sig_guest_activities', [
                'guest_id' => $guestId,
                'session_id' => session_id(),
                'activity_type' => $activityType,
                'activity_data' => $activityData ? json_encode($activityData) : null,
                'ip_address' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ]);
            
            // Update guest summary
            $this->updateGuestSummary($guestId, $ip, $activityType);
            
            return true;
        } catch (\Exception $e) {
            error_log("Guest activity logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update guest activity summary
     */
    private function updateGuestSummary($guestId, $ip, $activityType) {
        $summary = $this->db->fetchOne(
            'SELECT * FROM sig_guest_activity_summary WHERE guest_id = ?',
            [$guestId]
        );
        
        if ($summary) {
            // Update existing
            $updates = [
                'last_seen_at' => date('Y-m-d H:i:s'),
                'total_activities' => Database::raw('total_activities + 1')
            ];
            
            switch ($activityType) {
                case 'create_signature':
                    $updates['total_signatures_created'] = Database::raw('total_signatures_created + 1');
                    break;
                case 'copy_html':
                    $updates['total_copy_html'] = Database::raw('total_copy_html + 1');
                    break;
                case 'download_html':
                    $updates['total_downloads'] = Database::raw('total_downloads + 1');
                    break;
            }
            
            $this->db->update('sig_guest_activity_summary', $updates, 'guest_id = ?', [$guestId]);
        } else {
            // Create new
            $data = [
                'guest_id' => $guestId,
                'ip_address' => $ip,
                'total_activities' => 1,
                'total_signatures_created' => $activityType === 'create_signature' ? 1 : 0,
                'total_copy_html' => $activityType === 'copy_html' ? 1 : 0,
                'total_downloads' => $activityType === 'download_html' ? 1 : 0,
                'first_seen_at' => date('Y-m-d H:i:s'),
                'last_seen_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('sig_guest_activity_summary', $data);
        }
    }
    
    /**
     * Check if activity is abusive
     */
    private function isAbusive($guestId, $ip, $activityType) {
        // Check if already flagged
        $summary = $this->db->fetchOne(
            'SELECT is_flagged FROM sig_guest_activity_summary WHERE guest_id = ? OR ip_address = ?',
            [$guestId, $ip]
        );
        
        if ($summary && $summary['is_flagged']) {
            return true;
        }
        
        // Check rate limits (last 1 hour)
        $oneHourAgo = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $recentCount = $this->db->fetchOne(
            'SELECT COUNT(*) as count FROM sig_guest_activities 
             WHERE (guest_id = ? OR ip_address = ?) AND created_at >= ?',
            [$guestId, $ip, $oneHourAgo]
        );
        
        // Flag if more than 100 activities in 1 hour
        if ($recentCount && $recentCount['count'] > 100) {
            $this->flagGuest($guestId, $ip, 'Rate limit exceeded: ' . $recentCount['count'] . ' activities in 1 hour');
            return true;
        }
        
        // Check for rapid-fire actions (same action within 1 second)
        $oneSecondAgo = date('Y-m-d H:i:s', strtotime('-1 second'));
        
        $rapidFire = $this->db->fetchOne(
            'SELECT COUNT(*) as count FROM sig_guest_activities 
             WHERE (guest_id = ? OR ip_address = ?) 
             AND activity_type = ? 
             AND created_at >= ?',
            [$guestId, $ip, $activityType, $oneSecondAgo]
        );
        
        if ($rapidFire && $rapidFire['count'] > 0) {
            return true; // Too fast, likely bot
        }
        
        return false;
    }
    
    /**
     * Flag a guest as abusive
     */
    private function flagGuest($guestId, $ip, $reason) {
        $this->db->query(
            'UPDATE sig_guest_activity_summary 
             SET is_flagged = 1, flag_reason = ? 
             WHERE guest_id = ? OR ip_address = ?',
            [$reason, $guestId, $ip]
        );
    }
    
    /**
     * Get flagged guests (admin view)
     */
    public function getFlaggedGuests($limit = 50) {
        return $this->db->fetchAll(
            'SELECT * FROM sig_guest_activity_summary 
             WHERE is_flagged = 1 
             ORDER BY last_seen_at DESC 
             LIMIT ?',
            [$limit]
        );
    }
    
    /**
     * Get guest activity stats
     */
    public function getGuestStats($days = 30) {
        $startDate = date('Y-m-d', strtotime("-{$days} days"));
        
        return $this->db->fetchOne("
            SELECT 
                COUNT(DISTINCT guest_id) as unique_guests,
                COUNT(*) as total_activities,
                SUM(CASE WHEN activity_type = 'create_signature' THEN 1 ELSE 0 END) as signatures_created,
                SUM(CASE WHEN activity_type = 'copy_html' THEN 1 ELSE 0 END) as copy_html_count,
                SUM(CASE WHEN activity_type = 'download_html' THEN 1 ELSE 0 END) as download_count
            FROM sig_guest_activities
            WHERE created_at >= ?
        ", [$startDate]);
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
                   'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key]) && filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) {
                return $_SERVER[$key];
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? null;
    }
}
