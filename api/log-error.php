<?php
/**
 * Error Logging Endpoint
 * Logs errors and sends email notifications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Load configuration (env first, config file fallback)
$mailgunDomain = getenv('MAILGUN_DOMAIN');
$mailgunApiKey = getenv('MAILGUN_API_KEY');
$configPath = __DIR__ . '/../config/config.php';

if ((!$mailgunDomain || !$mailgunApiKey) && file_exists($configPath)) {
    $config = require $configPath;
    $mailgunDomain = $mailgunDomain ?: ($config['mailgun']['domain'] ?? null);
    $mailgunApiKey = $mailgunApiKey ?: ($config['mailgun']['api_key'] ?? null);
}

if (!$mailgunDomain || !$mailgunApiKey) {
    error_log('Mailgun credentials missing for log-error.php');
    echo json_encode(['success' => false, 'error' => 'Mail service not configured']);
    exit;
}

$to = 'support@ironcrestsoftware.com';
$from = 'Error Monitor <errors@' . $mailgunDomain . '>';

try {
    // Get error data
    $errorCode = $_GET['code'] ?? 'Unknown';
    $page = $_GET['page'] ?? 'Unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $referer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
    $timestamp = date('Y-m-d H:i:s');
    
    // Build email
    $subject = "[$errorCode Error] Email Signature Generator";
    
    $message = "
Error Alert - Email Signature Generator
========================================

Error Code: $errorCode
Page: $page
Time: $timestamp

User Information:
-----------------
IP Address: $ip
User Agent: $userAgent
Referrer: $referer

This is an automated error notification.
    ";
    
    // Send via Mailgun (only if not in development)
    if ($_SERVER['HTTP_HOST'] !== 'localhost') {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.mailgun.net/v3/{$mailgunDomain}/messages");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "api:{$mailgunApiKey}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'from' => $from,
            'to' => $to,
            'subject' => $subject,
            'text' => $message,
            'o:tag' => ['error-notification', "error-{$errorCode}"]
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("Mailgun error notification failed: HTTP {$httpCode}");
        }
    }
    
    // Log to file as backup
    $logFile = __DIR__ . '/../logs/errors.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] %s Error - Page: %s - IP: %s - UA: %s\n",
        $timestamp,
        $errorCode,
        $page,
        $ip,
        substr($userAgent, 0, 100)
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error logging failed: " . $e->getMessage());
    echo json_encode(['success' => false]);
}
