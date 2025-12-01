<?php
/**
 * Export API
 * POST /api/export.php - Email signature to user
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
use Ironcrest\Signature\EmailService;
use Ironcrest\Signature\Renderer;
use Ironcrest\Signature\Signature;

try {
    $config = require __DIR__ . '/../config/config.php';
    $db = Database::getInstance($config['database']);
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !isset($input['templateKey']) || !isset($input['config'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        exit;
    }
    
    $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid email address']);
        exit;
    }
    
    // Render signature HTML
    $renderer = new Renderer();
    $signatureHtml = $renderer->render($input['config'], $input['templateKey']);
    
    // Get or create signature UUID for re-edit link
    $publicUuid = $input['uuid'] ?? null;
    if (!$publicUuid) {
        // Create new signature
        $signatureModel = new Signature($db);
        
        // Get/create user
        $userSql = 'SELECT id FROM sig_users WHERE email_norm = ?';
        $user = $db->fetchOne($userSql, [strtolower($email)]);
        
        if (!$user) {
            $userId = $db->insert('sig_users', [
                'email' => $email,
                'email_norm' => strtolower($email),
            ]);
        } else {
            $userId = $user['id'];
        }
        
        $result = $signatureModel->create($userId, $input['templateKey'], $input['config']);
        $publicUuid = $result['uuid'];
    }
    
    // Send email
    $mailgunConfig = $config['mailgun'];
    $mailgunConfig['app_url'] = $config['app']['url'];
    $emailService = new EmailService($mailgunConfig);
    
    $name = $input['config']['identity']['name'] ?? 'there';
    $result = $emailService->sendSignatureDelivery($email, $name, $signatureHtml, $publicUuid);
    
    if (!$result['success']) {
        error_log("Email send failed: " . json_encode($result));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send email. Please try again.',
            'debug' => $config['app']['debug'] ? ($result['error'] ?? 'Unknown error') : null,
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Signature sent to ' . $email,
        'uuid' => $publicUuid,
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $config['app']['debug'] ?? false ? $e->getMessage() : 'Internal server error',
    ]);
}
