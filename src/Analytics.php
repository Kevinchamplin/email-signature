<?php
namespace Ironcrest\Signature;

use PDO;

/**
 * Analytics Tracking System
 * Handles view tracking, click tracking, and analytics data
 */
class Analytics {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Record a signature view (tracking pixel hit)
     */
    public function recordView($signatureId, $userId, $metadata = []) {
        try {
            $this->db->insert('sig_analytics_views', [
                'signature_id' => $signatureId,
                'user_id' => $userId,
                'viewed_at' => date('Y-m-d H:i:s'),
                'ip_address' => $metadata['ip'] ?? $this->getClientIP(),
                'user_agent' => $metadata['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                'email_client' => $metadata['email_client'] ?? $this->detectEmailClient(),
                'device_type' => $metadata['device_type'] ?? $this->detectDeviceType(),
                'country' => $metadata['country'] ?? null,
                'city' => $metadata['city'] ?? null,
            ]);
            
            // Update daily aggregates
            $this->updateDailyStats($signatureId, $userId, 'view');
            
            return true;
        } catch (\Exception $e) {
            error_log("Analytics view recording failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record a link click
     */
    public function recordClick($signatureId, $userId, $linkType, $linkUrl, $metadata = []) {
        try {
            $this->db->insert('sig_analytics_clicks', [
                'signature_id' => $signatureId,
                'user_id' => $userId,
                'link_type' => $linkType,
                'link_url' => $linkUrl,
                'clicked_at' => date('Y-m-d H:i:s'),
                'ip_address' => $metadata['ip'] ?? $this->getClientIP(),
                'user_agent' => $metadata['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
                'device_type' => $metadata['device_type'] ?? $this->detectDeviceType(),
                'country' => $metadata['country'] ?? null,
                'city' => $metadata['city'] ?? null,
                'referrer' => $metadata['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? null),
            ]);
            
            // Update daily aggregates
            $this->updateDailyStats($signatureId, $userId, 'click', $linkType);
            
            return true;
        } catch (\Exception $e) {
            error_log("Analytics click recording failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create a tracking link
     */
    public function createTrackingLink($signatureId, $userId, $linkType, $destinationUrl) {
        $shortCode = $this->generateShortCode();
        
        try {
            $this->db->insert('sig_tracking_links', [
                'signature_id' => $signatureId,
                'user_id' => $userId,
                'short_code' => $shortCode,
                'link_type' => $linkType,
                'destination_url' => $destinationUrl,
                'created_at' => date('Y-m-d H:i:s'),
                'is_active' => 1,
            ]);
            
            return $shortCode;
        } catch (\Exception $e) {
            error_log("Tracking link creation failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get tracking link by short code
     */
    public function getTrackingLink($shortCode) {
        return $this->db->fetchOne(
            'SELECT * FROM sig_tracking_links WHERE short_code = ? AND is_active = 1',
            [$shortCode]
        );
    }
    
    /**
     * Get analytics summary for a signature
     */
    public function getSignatureAnalytics($signatureId, $dateRange = 30) {
        $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));
        
        // Get totals
        $totals = $this->db->fetchOne("
            SELECT 
                COALESCE(SUM(total_views), 0) as total_views,
                COALESCE(SUM(total_clicks), 0) as total_clicks,
                COALESCE(SUM(unique_viewers), 0) as unique_viewers,
                COALESCE(SUM(email_clicks), 0) as email_clicks,
                COALESCE(SUM(phone_clicks), 0) as phone_clicks,
                COALESCE(SUM(website_clicks), 0) as website_clicks,
                COALESCE(SUM(linkedin_clicks), 0) as linkedin_clicks,
                COALESCE(SUM(twitter_clicks), 0) as twitter_clicks,
                COALESCE(SUM(github_clicks), 0) as github_clicks,
                COALESCE(SUM(calendly_clicks), 0) as calendly_clicks,
                COALESCE(SUM(custom_clicks), 0) as custom_clicks,
                COALESCE(SUM(desktop_views), 0) as desktop_views,
                COALESCE(SUM(mobile_views), 0) as mobile_views,
                COALESCE(SUM(tablet_views), 0) as tablet_views
            FROM sig_analytics_daily
            WHERE signature_id = ? AND date >= ?
        ", [$signatureId, $startDate]);
        
        // Calculate CTR
        $totals['ctr'] = $totals['total_views'] > 0 
            ? round(($totals['total_clicks'] / $totals['total_views']) * 100, 2) 
            : 0;
        
        // Get daily breakdown
        $daily = $this->db->fetchAll("
            SELECT 
                date,
                total_views,
                total_clicks,
                unique_viewers
            FROM sig_analytics_daily
            WHERE signature_id = ? AND date >= ?
            ORDER BY date ASC
        ", [$signatureId, $startDate]);
        
        // Get top links
        $topLinks = $this->db->fetchAll("
            SELECT 
                link_type,
                COUNT(*) as click_count,
                COUNT(DISTINCT ip_address) as unique_clicks
            FROM sig_analytics_clicks
            WHERE signature_id = ? AND clicked_at >= ?
            GROUP BY link_type
            ORDER BY click_count DESC
            LIMIT 10
        ", [$signatureId, $startDate]);
        
        // Get geographic data
        $geographic = $this->db->fetchAll("
            SELECT 
                country,
                COUNT(*) as view_count
            FROM sig_analytics_views
            WHERE signature_id = ? AND viewed_at >= ? AND country IS NOT NULL
            GROUP BY country
            ORDER BY view_count DESC
            LIMIT 10
        ", [$signatureId, $startDate]);
        
        return [
            'totals' => $totals,
            'daily' => $daily,
            'top_links' => $topLinks,
            'geographic' => $geographic,
        ];
    }
    
    /**
     * Get analytics for all user's signatures
     */
    public function getUserAnalytics($userId, $dateRange = 30) {
        $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));
        
        return $this->db->fetchAll("
            SELECT 
                s.id,
                s.title,
                s.template_key,
                COALESCE(SUM(d.total_views), 0) as total_views,
                COALESCE(SUM(d.total_clicks), 0) as total_clicks,
                COALESCE(SUM(d.unique_viewers), 0) as unique_viewers,
                CASE 
                    WHEN SUM(d.total_views) > 0 
                    THEN ROUND((SUM(d.total_clicks) / SUM(d.total_views)) * 100, 2)
                    ELSE 0 
                END as ctr
            FROM sig_signatures s
            LEFT JOIN sig_analytics_daily d ON s.id = d.signature_id AND d.date >= ?
            WHERE s.user_id = ? AND s.is_deleted = 0
            GROUP BY s.id, s.title, s.template_key
            ORDER BY total_views DESC
        ", [$startDate, $userId]);
    }
    
    /**
     * Update daily aggregated stats
     */
    private function updateDailyStats($signatureId, $userId, $type, $linkType = null) {
        $today = date('Y-m-d');
        
        // Check if record exists for today
        $existing = $this->db->fetchOne(
            'SELECT id FROM sig_analytics_daily WHERE signature_id = ? AND date = ?',
            [$signatureId, $today]
        );
        
        if ($existing) {
            // Update existing record
            $updates = [];
            
            if ($type === 'view') {
                $updates = [
                    'total_views' => Database::raw('total_views + 1'),
                ];
            } elseif ($type === 'click' && $linkType) {
                $updates = [
                    'total_clicks' => Database::raw('total_clicks + 1'),
                    $linkType . '_clicks' => Database::raw($linkType . '_clicks + 1'),
                ];
            }
            
            if (!empty($updates)) {
                $this->db->update('sig_analytics_daily', $updates, 'id = ?', [$existing['id']]);
            }
        } else {
            // Create new record
            $data = [
                'signature_id' => $signatureId,
                'user_id' => $userId,
                'date' => $today,
                'total_views' => 0,
                'total_clicks' => 0,
                'unique_viewers' => 0,
            ];
            
            if ($type === 'view') {
                $data['total_views'] = 1;
            } elseif ($type === 'click' && $linkType) {
                $data['total_clicks'] = 1;
                $data[$linkType . '_clicks'] = 1;
            }
            
            $this->db->insert('sig_analytics_daily', $data);
        }
    }
    
    /**
     * Generate unique short code for tracking links
     */
    private function generateShortCode($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        // Check if code already exists
        $exists = $this->db->fetchOne(
            'SELECT id FROM sig_tracking_links WHERE short_code = ?',
            [$code]
        );
        
        // If exists, generate a new one
        if ($exists) {
            return $this->generateShortCode($length);
        }
        
        return $code;
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
    
    /**
     * Detect email client from user agent
     */
    private function detectEmailClient() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (stripos($userAgent, 'Outlook') !== false) return 'Outlook';
        if (stripos($userAgent, 'Thunderbird') !== false) return 'Thunderbird';
        if (stripos($userAgent, 'Apple Mail') !== false) return 'Apple Mail';
        if (stripos($userAgent, 'Gmail') !== false) return 'Gmail';
        if (stripos($userAgent, 'Yahoo') !== false) return 'Yahoo Mail';
        
        return 'Unknown';
    }
    
    /**
     * Detect device type from user agent
     */
    private function detectDeviceType() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile/i', $userAgent)) {
            return 'mobile';
        }
        
        if (preg_match('/tablet|ipad|playbook|silk/i', $userAgent)) {
            return 'tablet';
        }
        
        return 'desktop';
    }
    
    /**
     * Get or create analytics settings for user
     */
    public function getUserSettings($userId) {
        $settings = $this->db->fetchOne(
            'SELECT * FROM sig_analytics_settings WHERE user_id = ?',
            [$userId]
        );
        
        if (!$settings) {
            // Create default settings
            $this->db->insert('sig_analytics_settings', [
                'user_id' => $userId,
                'tracking_enabled' => 1,
                'track_views' => 1,
                'track_clicks' => 1,
                'track_location' => 1,
                'track_device' => 1,
            ]);
            
            $settings = $this->getUserSettings($userId);
        }
        
        return $settings;
    }
    
    /**
     * Update user analytics settings
     */
    public function updateUserSettings($userId, $settings) {
        return $this->db->update(
            'sig_analytics_settings',
            $settings,
            'user_id = ?',
            [$userId]
        );
    }
}
