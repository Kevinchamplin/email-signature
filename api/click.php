<?php
/**
 * Click Tracking & Redirect Endpoint
 * Tracks link clicks and redirects to destination
 * 
 * Usage: https://apps.ironcrestsoftware.com/email-signature/api/click.php?c={short_code}
 */

// Disable error display
ini_set('display_errors', 0);
error_reporting(0);

// Load environment variables
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

try {
    // Get short code
    $shortCode = $_GET['c'] ?? null;
    
    if (!$shortCode) {
        header('HTTP/1.1 400 Bad Request');
        echo 'Invalid tracking link';
        exit;
    }
    
    // Database connection using environment variables
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Get tracking link
    $stmt = $pdo->prepare('
        SELECT signature_id, user_id, link_type, destination_url 
        FROM sig_tracking_links 
        WHERE short_code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())
    ');
    $stmt->execute([$shortCode]);
    $link = $stmt->fetch();
    
    if (!$link) {
        header('HTTP/1.1 404 Not Found');
        echo 'Link not found or expired';
        exit;
    }
    
    // Detect device type and email client
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $deviceType = detectDeviceType($userAgent);
    $emailClient = detectEmailClient($userAgent);
    
    // Record the click
    $stmt = $pdo->prepare('
        INSERT INTO sig_analytics_clicks 
        (signature_id, user_id, link_type, link_url, ip_address, user_agent, device_type, clicked_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgentTruncated = substr($userAgent, 0, 255);
    
    $stmt->execute([
        $link['signature_id'], 
        $link['user_id'], 
        $link['link_type'], 
        $link['destination_url'],
        $ipAddress,
        $userAgentTruncated,
        $deviceType
    ]);
    
    // Redirect to destination
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $link['destination_url']);
    exit;
    
} catch (Exception $e) {
    error_log("Click tracking error: " . $e->getMessage());
    
    // If we have a destination URL, redirect anyway
    if (isset($link['destination_url'])) {
        header('Location: ' . $link['destination_url']);
        exit;
    }
    
    header('HTTP/1.1 500 Internal Server Error');
    echo 'An error occurred';
    exit;
}

/**
 * Detect device type from user agent
 */
function detectDeviceType($userAgent) {
    $userAgent = strtolower($userAgent);
    
    // Mobile devices
    if (preg_match('/mobile|android|iphone|ipod|blackberry|windows phone/', $userAgent)) {
        return 'mobile';
    }
    
    // Tablets
    if (preg_match('/tablet|ipad|kindle|silk/', $userAgent)) {
        return 'tablet';
    }
    
    // Desktop (default)
    return 'desktop';
}

/**
 * Detect email client from user agent
 */
function detectEmailClient($userAgent) {
    $userAgent = strtolower($userAgent);
    
    if (strpos($userAgent, 'outlook') !== false) return 'Outlook';
    if (strpos($userAgent, 'thunderbird') !== false) return 'Thunderbird';
    if (strpos($userAgent, 'apple mail') !== false) return 'Apple Mail';
    if (strpos($userAgent, 'gmail') !== false) return 'Gmail';
    if (strpos($userAgent, 'yahoo') !== false) return 'Yahoo Mail';
    if (strpos($userAgent, 'spark') !== false) return 'Spark';
    if (strpos($userAgent, 'airmail') !== false) return 'Airmail';
    
    return 'Unknown';
}
