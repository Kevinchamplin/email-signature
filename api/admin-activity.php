<?php
/**
 * Admin Activity API
 * Provides user activity data and statistics
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    // Database connection
    $pdo = new PDO(
        'mysql:host=' . getenv('DB_HOST') . ';dbname=' . getenv('DB_NAME') . ';charset=utf8mb4',
        getenv('DB_USER'),
        getenv('DB_PASS'),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Load UnifiedAuth class
    require_once __DIR__ . '/../../auth/UnifiedAuth.php';
    $auth = new UnifiedAuth($pdo);
    
    // Validate authentication using unified auth
    if (!$auth->isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
    
    // Get current user
    $currentUser = $auth->getCurrentUser();
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid session']);
        exit;
    }
    
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'summary':
            // Get summary statistics
            $stats = [];
            
            // Total users (unified auth)
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
            $stats['total_users'] = $stmt->fetch()['count'] ?? 0;
            
            // Total signatures (email signature app)
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM sig_signatures');
            $stats['total_signatures'] = $stmt->fetch()['count'] ?? 0;
            
            // Total activities (check if table exists)
            try {
                $stmt = $pdo->query('SELECT COUNT(*) as count FROM sig_activity_log');
                $stats['total_activities'] = $stmt->fetch()['count'] ?? 0;
            } catch (Exception $e) {
                $stats['total_activities'] = 0;
            }
            
            // Active sessions (unified auth)
            $stmt = $pdo->query('SELECT COUNT(*) as count FROM user_sessions WHERE expires_at > NOW()');
            $stats['active_sessions'] = $stmt->fetch()['count'] ?? 0;
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        case 'active_users':
            // Get active users (users with recent activity)
            try {
                // Try query with tier column first
                try {
                    $stmt = $pdo->prepare('
                        SELECT u.id, u.email, u.first_name, u.last_name, 
                               COALESCE(u.tier, "free") as tier, u.created_at,
                               COUNT(DISTINCT s.id) as signature_count,
                               MAX(s.updated_at) as last_activity,
                               COUNT(DISTINCT sess.id) as active_sessions
                        FROM users u
                        LEFT JOIN sig_signatures s ON u.id = s.user_id
                        LEFT JOIN user_sessions sess ON u.id = sess.user_id AND sess.expires_at > NOW()
                        GROUP BY u.id, u.email, u.first_name, u.last_name, u.tier, u.created_at
                        HAVING signature_count > 0 OR active_sessions > 0
                        ORDER BY last_activity DESC
                        LIMIT 100
                    ');
                    $stmt->execute();
                    $users = $stmt->fetchAll();
                } catch (Exception $e) {
                    // Fallback query without tier column
                    $stmt = $pdo->prepare('
                        SELECT u.id, u.email, u.first_name, u.last_name, 
                               "free" as tier, u.created_at,
                               COUNT(DISTINCT s.id) as signature_count,
                               MAX(s.updated_at) as last_activity,
                               COUNT(DISTINCT sess.id) as active_sessions
                        FROM users u
                        LEFT JOIN sig_signatures s ON u.id = s.user_id
                        LEFT JOIN user_sessions sess ON u.id = sess.user_id AND sess.expires_at > NOW()
                        GROUP BY u.id, u.email, u.first_name, u.last_name, u.created_at
                        HAVING signature_count > 0 OR active_sessions > 0
                        ORDER BY last_activity DESC
                        LIMIT 100
                    ');
                    $stmt->execute();
                    $users = $stmt->fetchAll();
                }
                
                echo json_encode([
                    'success' => true,
                    'users' => $users
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Database error: ' . $e->getMessage()
                ]);
            }
            break;
            
        case 'recent_activity':
            // Get recent activity log
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            
            try {
                $stmt = $pdo->prepare('
                    SELECT a.*, u.email
                    FROM sig_activity_log a
                    LEFT JOIN users u ON a.user_id = u.id
                    ORDER BY a.created_at DESC
                    LIMIT ?
                ');
                $stmt->execute([$limit]);
                $activities = $stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'activities' => $activities
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => true,
                    'activities' => [],
                    'note' => 'Activity log table not available'
                ]);
            }
            break;
            
        case 'user_details':
            // Get detailed info about a specific user
            $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
            
            if (!$userId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'User ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'User not found']);
                exit;
            }
            
            // Get user's signatures
            $stmt = $pdo->prepare('
                SELECT id, template_key, created_at, updated_at 
                FROM sig_signatures 
                WHERE user_id = ? 
                ORDER BY updated_at DESC
            ');
            $stmt->execute([$userId]);
            $signatures = $stmt->fetchAll();
            
            // Get user's recent activity (if table exists)
            $activities = [];
            try {
                $stmt = $pdo->prepare('
                    SELECT * FROM sig_activity_log 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 20
                ');
                $stmt->execute([$userId]);
                $activities = $stmt->fetchAll();
            } catch (Exception $e) {
                // Activity log table doesn't exist, that's ok
            }
            
            echo json_encode([
                'success' => true,
                'user' => $user,
                'signatures' => $signatures,
                'activities' => $activities
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action',
                'available_actions' => ['summary', 'active_users', 'recent_activity', 'user_details']
            ]);
    }
    
} catch (\Exception $e) {
    error_log('Admin Activity API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}
