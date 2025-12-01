<?php
/**
 * Tracking Pixel Endpoint
 * Records signature views when the 1x1 pixel image loads in email
 * 
 * Usage: <img src="https://apps.ironcrestsoftware.com/email-signature/api/pixel.php?s={signature_id}&u={user_id}" width="1" height="1" />
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
    // Get parameters
    $signatureId = $_GET['s'] ?? null;
    $userId = $_GET['u'] ?? null;
    
    // Validate required parameters
    if (!$signatureId || !$userId) {
        outputPixel();
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
    
    // Detect device type from user agent
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $deviceType = detectDeviceType($userAgent);
    $emailClient = detectEmailClient($userAgent);
    
    // Get IP and location data
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $locationData = getLocationFromIP($ipAddress);
    
    // Record the view in analytics
    $stmt = $pdo->prepare('
        INSERT INTO sig_analytics_views (signature_id, user_id, ip_address, user_agent, device_type, email_client, country, city, viewed_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ');
    
    $userAgentTruncated = substr($userAgent, 0, 255);
    
    $stmt->execute([
        $signatureId, 
        $userId, 
        $ipAddress, 
        $userAgentTruncated, 
        $deviceType, 
        $emailClient,
        $locationData['country'] ?? null,
        $locationData['city'] ?? null
    ]);
    
} catch (Exception $e) {
    // Silently fail - don't break email display
    error_log("Pixel tracking error: " . $e->getMessage());
}

// Always output a 1x1 transparent pixel
outputPixel();

/**
 * Get location data from IP address
 */
function getLocationFromIP($ipAddress) {
    // Skip local/private IPs
    if ($ipAddress === 'unknown' || 
        filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['country' => null, 'city' => null];
    }
    
    try {
        // Use ip-api.com (free service, 1000 requests/month)
        $url = "http://ip-api.com/json/{$ipAddress}?fields=status,country,countryCode,city";
        
        // Set a short timeout to avoid blocking email display
        $context = stream_context_create([
            'http' => [
                'timeout' => 2, // 2 second timeout
                'method' => 'GET'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            return ['country' => null, 'city' => null];
        }
        
        $data = json_decode($response, true);
        
        if ($data && $data['status'] === 'success') {
            return [
                'country' => $data['countryCode'] ?? null,
                'city' => $data['city'] ?? null
            ];
        }
        
        return ['country' => null, 'city' => null];
        
    } catch (Exception $e) {
        // Silently fail - don't break tracking
        error_log("Geolocation error: " . $e->getMessage());
        return ['country' => null, 'city' => null];
    }
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

/**
 * Output a 1x1 transparent GIF pixel
 */
function outputPixel() {
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
    
    // 1x1 transparent GIF (43 bytes)
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}
