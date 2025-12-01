<?php
/**
 * Analytics Tracking API
 * POST /api/track.php - Track user events
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use Ironcrest\Signature\Database;

try {
    $config = require __DIR__ . '/../config/config.php';
    $db = Database::getInstance($config['database']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['event_type'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing event_type']);
        exit;
    }
    
    // Get IP address and hash it for privacy
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ipHash = hash('sha256', $ip . $config['security']['ip_salt']);
    
    // Get user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Prepare event data
    $eventData = [
        'user_id' => $input['user_id'] ?? null,
        'signature_id' => $input['signature_id'] ?? null,
        'event_type' => $input['event_type'],
        'ip_hash' => $ipHash,
        'user_agent' => $userAgent ? substr($userAgent, 0, 255) : null,
        'meta_json' => json_encode($input['meta'] ?? []),
    ];
    
    // Insert event
    $db->insert('sig_events', $eventData);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log('Track API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to track event',
    ]);
}
