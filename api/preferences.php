<?php
/**
 * User Preferences API
 * GET /api/preferences.php - Get user's saved preferences
 * POST /api/preferences.php - Save/update user's preferences
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Ironcrest\Signature\Auth;
use Ironcrest\Signature\Database;

try {
    $config = require __DIR__ . '/../config/config.php';
    
    // Initialize Database
    $db = Database::getInstance($config['database']);
    
    // Check authentication
    $auth = new Auth($db, $config);
    
    // Get token from cookie or Authorization header (unified auth first, fallback to legacy)
    $token = $_COOKIE['ironcrest_session'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['session_token'] ?? null;
    $token = str_replace('Bearer ', '', $token);
    
    // Debug logging
    error_log('=== PREFERENCES API DEBUG ===');
    error_log('Request URI: ' . $_SERVER['REQUEST_URI']);
    error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('HTTP_COOKIE header: ' . ($_SERVER['HTTP_COOKIE'] ?? 'not set'));
    error_log('$_COOKIE array: ' . print_r($_COOKIE, true));
    error_log('session_token in $_COOKIE: ' . (isset($_COOKIE['session_token']) ? 'YES' : 'NO'));
    error_log('Token extracted: ' . ($token ? substr($token, 0, 20) . '...' : 'MISSING'));
    error_log('Authorization header: ' . ($_SERVER['HTTP_AUTHORIZATION'] ?? 'not set'));
    
    if (!$token) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'No session token provided'
        ]);
        exit;
    }
    
    $user = $auth->validateSession($token);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired session'
        ]);
        exit;
    }
    
    $userId = $user['id'];
    
    // GET - Retrieve preferences
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $prefs = $db->fetchOne('
            SELECT 
                name, title, pronouns,
                company_name, logo_url,
                email, phone, website, calendly,
                social_links, branding_preferences, addons,
                updated_at
            FROM sig_user_preferences
            WHERE user_id = ?
        ', [$userId]);
        
        if ($prefs) {
            // Decode JSON fields
            $prefs['social_links'] = json_decode($prefs['social_links'] ?? '{}', true);
            $prefs['branding_preferences'] = json_decode($prefs['branding_preferences'] ?? '{}', true);
            $prefs['addons'] = json_decode($prefs['addons'] ?? '{}', true);
            
            echo json_encode([
                'success' => true,
                'preferences' => $prefs
            ]);
        } else {
            // No preferences yet - return empty structure
            echo json_encode([
                'success' => true,
                'preferences' => null,
                'message' => 'No saved preferences yet'
            ]);
        }
        exit;
    }
    
    // POST - Save/update preferences
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || !isset($input['email'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Name and email are required'
            ]);
            exit;
        }
        
        // Prepare data
        $name = $input['name'];
        $title = $input['title'] ?? null;
        $pronouns = $input['pronouns'] ?? null;
        $companyName = $input['company_name'] ?? null;
        $logoUrl = $input['logo_url'] ?? null;
        $email = $input['email'];
        $phone = $input['phone'] ?? null;
        $website = $input['website'] ?? null;
        $calendly = $input['calendly'] ?? null;
        $socialLinks = json_encode($input['social_links'] ?? []);
        $brandingPrefs = json_encode($input['branding_preferences'] ?? []);
        $addons = json_encode($input['addons'] ?? []);
        
        // Insert or update
        $db->query('
            INSERT INTO sig_user_preferences (
                user_id, name, title, pronouns,
                company_name, logo_url,
                email, phone, website, calendly,
                social_links, branding_preferences, addons
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                title = VALUES(title),
                pronouns = VALUES(pronouns),
                company_name = VALUES(company_name),
                logo_url = VALUES(logo_url),
                email = VALUES(email),
                phone = VALUES(phone),
                website = VALUES(website),
                calendly = VALUES(calendly),
                social_links = VALUES(social_links),
                branding_preferences = VALUES(branding_preferences),
                addons = VALUES(addons),
                updated_at = CURRENT_TIMESTAMP
        ', [
            $userId, $name, $title, $pronouns,
            $companyName, $logoUrl,
            $email, $phone, $website, $calendly,
            $socialLinks, $brandingPrefs, $addons
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Preferences saved successfully'
        ]);
        exit;
    }
    
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to process preferences',
        'message' => $config['app']['debug'] ?? false ? $e->getMessage() : 'Internal server error'
    ]);
}
