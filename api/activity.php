<?php
/**
 * User Activity API
 * POST /api/activity.php - Log user activity
 * GET /api/activity.php?action=summary - Get user activity summary
 * GET /api/activity.php?action=history - Get user activity history
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/UserActivity.php';
require_once __DIR__ . '/../src/Auth.php';

use Ironcrest\Signature\Database;
use Ironcrest\Signature\UserActivity;
use Ironcrest\Signature\Auth;

try {
    // Database connection
    $config = [
        'host' => '815hosting.com',
        'name' => 'ironcrest_db',
        'username' => 'dbuser_ic',
        'password' => 'jg*ce4%0qgXAoMc3',
        'charset' => 'utf8mb4'
    ];
    
    $db = Database::getInstance($config);
    $activity = new UserActivity($db);
    $auth = new Auth($db);
    
    // Check if user is logged in or guest (unified auth first, fallback to legacy)
    $sessionToken = $_COOKIE['ironcrest_session'] ?? $_COOKIE['session_token'] ?? null;
    $session = null;
    $userId = null;
    $isGuest = true;
    
    if ($sessionToken) {
        $session = $auth->validateSession($sessionToken);
        if ($session) {
            $userId = $session['user_id'];
            $isGuest = false;
        }
    }
    
    // Handle POST - Log activity
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['activityType'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing activityType']);
            exit;
        }
        
        $activityType = $input['activityType'];
        $signatureId = $input['signatureId'] ?? null;
        $activityData = $input['data'] ?? null;
        
        if ($isGuest) {
            // Log guest activity
            $guestId = $input['guestId'] ?? null;
            
            if (!$guestId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Missing guestId for anonymous tracking']);
                exit;
            }
            
            $result = $activity->logGuest($guestId, $activityType, $activityData);
        } else {
            // Log authenticated user activity
            $result = $activity->log($userId, $activityType, $signatureId, $activityData);
        }
        
        echo json_encode([
            'success' => $result,
            'message' => $result ? 'Activity logged' : 'Failed to log activity',
            'isGuest' => $isGuest
        ]);
        exit;
    }
    
    // Handle GET - Retrieve activity data (requires authentication)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($isGuest) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required to view activity history']);
            exit;
        }
        
        $action = $_GET['action'] ?? 'summary';
        
        switch ($action) {
            case 'summary':
                $summary = $activity->getUserSummary($userId);
                echo json_encode([
                    'success' => true,
                    'summary' => $summary
                ]);
                break;
                
            case 'history':
                $limit = $_GET['limit'] ?? 50;
                $activityType = $_GET['type'] ?? null;
                $history = $activity->getUserActivity($userId, $limit, $activityType);
                echo json_encode([
                    'success' => true,
                    'history' => $history
                ]);
                break;
                
            case 'breakdown':
                $days = $_GET['days'] ?? 30;
                $breakdown = $activity->getActivityBreakdown($userId, $days);
                echo json_encode([
                    'success' => true,
                    'breakdown' => $breakdown
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
                break;
        }
        exit;
    }
    
} catch (Exception $e) {
    error_log('Activity API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
    ]);
}
