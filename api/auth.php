<?php
/**
 * Authentication API - Email Signature App
 * Now using Unified Ironcrest Auth System
 * POST /api/auth.php?action=register|login|logout|validate|features
 */

header('Content-Type: application/json');
$allowedOrigins = [
    'https://apps.ironcrestsoftware.com',
    'https://ironcrestsoftware.com',
    'https://www.ironcrestsoftware.com'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if ($origin && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Ironcrest-Session');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../../shared/src/IroncrestAuth.php';

use Ironcrest\Signature\Database;
use Ironcrest\Shared\IroncrestAuth;

try {
    $config = require __DIR__ . '/../config/config.php';
    $db = Database::getInstance($config['database']);
    $auth = new IroncrestAuth($db, $config);
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'register':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['email']) || !isset($input['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email and password required']);
                exit;
            }
            
            // Auto-grandfather early users
            $result = $auth->register(
                $input['email'],
                $input['password'],
                $input['name'] ?? null,
                true // is_grandfathered
            );
            
            if ($result['success']) {
                // Auto-login after registration
                $loginResult = $auth->login($input['email'], $input['password'], 'email-signature');
                if ($loginResult['success']) {
                    $result['session_token'] = $loginResult['session_token'];
                    $result['user'] = $loginResult['user'];
                    setcookie('ironcrest_session', $loginResult['session_token'], time() + 2592000, '/', 'apps.ironcrestsoftware.com', true, true);
                }
                http_response_code(201);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
            break;
            
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['email']) || !isset($input['password'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Email and password required']);
                exit;
            }
            
            $result = $auth->login($input['email'], $input['password'], 'email-signature');
            if ($result['success'] && !empty($result['session_token'])) {
                setcookie('ironcrest_session', $result['session_token'], time() + 2592000, '/', 'apps.ironcrestsoftware.com', true, true);
            }
            
            if (!$result['success']) {
                http_response_code(401);
            }
            
            echo json_encode($result);
            break;
            
        case 'logout':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }
            
            // Check both unified and legacy cookies
            $token = $_COOKIE['ironcrest_session']
                ?? ($_SERVER['HTTP_X_IRONCREST_SESSION'] ?? null)
                ?? $_SERVER['HTTP_AUTHORIZATION']
                ?? $_COOKIE['session_token']
                ?? null;
            $token = str_replace('Bearer ', '', $token ?? '');
            
            // Try to logout if token exists
            if ($token) {
                try {
                    $result = $auth->logout($token);
                } catch (\Exception $e) {
                    // Ignore errors - just clear cookies anyway
                    $result = ['success' => true];
                }
            } else {
                $result = ['success' => true, 'message' => 'No session to logout'];
            }
            
            // Always clear cookies server-side
            setcookie('ironcrest_session', '', time() - 3600, '/', 'apps.ironcrestsoftware.com', true, true);
            setcookie('session_token', '', time() - 3600, '/', 'apps.ironcrestsoftware.com', true, true);
            
            echo json_encode($result);
            break;
            
        case 'validate':
            $token = null;
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
            if (!$authHeader && function_exists('getallheaders')) {
                $headers = getallheaders();
                if (isset($headers['Authorization'])) {
                    $authHeader = $headers['Authorization'];
                }
                if (isset($headers['X-Ironcrest-Session']) && !$token) {
                    $token = $headers['X-Ironcrest-Session'];
                }
            }
            $candidates = [
                $_GET['session_token'] ?? null,
                $_GET['session'] ?? null,
                $_GET['token'] ?? null,
                $_COOKIE['ironcrest_session'] ?? null,
                $_SERVER['HTTP_X_IRONCREST_SESSION'] ?? null,
                $authHeader
            ];
            foreach ($candidates as $candidate) {
                if (empty($candidate)) {
                    continue;
                }
                $token = $candidate;
                break;
            }
            if ($token && stripos($token, 'Bearer ') === 0) {
                $token = substr($token, 7);
            }
            
            $result = $auth->validateSession($token);
            
            if (!$result['authenticated']) {
                http_response_code(401);
            }
            if ($result['authenticated'] && !empty($token)) {
                setcookie('ironcrest_session', $token, time() + 2592000, '/', 'apps.ironcrestsoftware.com', true, true);
            }
            
            echo json_encode($result);
            break;
            
        case 'features':
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['session_token'] ?? null;
            $token = str_replace('Bearer ', '', $token);
            
            $user = $auth->validateSession($token);
            
            if (!$user) {
                // Return free tier features
                $features = $db->fetchAll(
                    'SELECT feature_key, feature_name, description FROM sig_feature_access WHERE free_tier = 1'
                );
                
                echo json_encode([
                    'success' => true,
                    'features' => $features,
                    'tier' => 'free',
                    'is_grandfathered' => false,
                ]);
                exit;
            }
            
            $features = $auth->getUserFeatures($user['user_id']);
            
            echo json_encode([
                'success' => true,
                'features' => $features,
                'tier' => $user['account_tier'],
                'is_grandfathered' => $user['is_grandfathered'],
            ]);
            break;
            
        case 'check-feature':
            $featureKey = $_GET['feature'] ?? '';
            
            if (!$featureKey) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Feature key required']);
                exit;
            }
            
            $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_COOKIE['session_token'] ?? null;
            $token = str_replace('Bearer ', '', $token);
            
            $user = $auth->validateSession($token);
            
            if (!$user) {
                // Check if feature is free
                $feature = $db->fetchOne(
                    'SELECT free_tier FROM sig_feature_access WHERE feature_key = ?',
                    [$featureKey]
                );
                
                echo json_encode([
                    'success' => true,
                    'has_access' => $feature ? (bool)$feature['free_tier'] : false,
                    'requires_login' => true,
                ]);
                exit;
            }
            
            $hasAccess = $auth->hasFeatureAccess($user['user_id'], $featureKey);
            
            echo json_encode([
                'success' => true,
                'has_access' => $hasAccess,
                'requires_login' => false,
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log('Auth API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $config['app']['debug'] ?? false ? $e->getMessage() : 'Internal server error',
    ]);
}
