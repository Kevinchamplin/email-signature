<?php
/**
 * Signatures API
 * POST   /api/signatures.php - Create signature
 * GET    /api/signatures.php?id=123 - Get signature (auth)
 * GET    /api/signatures.php?uuid=abc - Get public signature
 * PUT    /api/signatures.php?id=123 - Update signature
 * DELETE /api/signatures.php?id=123 - Delete signature
 */

header('Content-Type: application/json');

// Set CORS headers based on environment
$allowedOrigins = explode(',', getenv('ALLOWED_ORIGINS') ?: 'https://ironcrestsoftware.com,https://apps.ironcrestsoftware.com');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://apps.ironcrestsoftware.com');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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
    
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // LIST signatures for dashboard
    if ($method === 'GET' && $action === 'list') {
        // Check authentication
        if (!$auth->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
            exit;
        }
        
        $currentUser = $auth->getCurrentUser();
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid session']);
            exit;
        }
        
        $userId = $currentUser['id'];
        
        try {
            // First check what columns exist
            $stmt = $pdo->query("DESCRIBE sig_signatures");
            $columns = $stmt->fetchAll();
            $columnNames = array_column($columns, 'Field');
            
            // Build SELECT based on available columns
            $selectFields = ['id'];
            
            if (in_array('title', $columnNames)) $selectFields[] = 'title';
            if (in_array('template_key', $columnNames)) $selectFields[] = 'template_key';
            if (in_array('config_json', $columnNames)) $selectFields[] = 'config_json';
            if (in_array('config', $columnNames)) $selectFields[] = 'config as config_json';
            if (in_array('public_uuid', $columnNames)) {
                $selectFields[] = 'public_uuid';
            } elseif (in_array('uuid', $columnNames)) {
                $selectFields[] = 'uuid as public_uuid';
            } else {
                // Use id as fallback for public_uuid if neither column exists
                $selectFields[] = 'id as public_uuid';
            }
            if (in_array('created_at', $columnNames)) $selectFields[] = 'created_at';
            if (in_array('updated_at', $columnNames)) $selectFields[] = 'updated_at';
            
            $sql = 'SELECT ' . implode(', ', $selectFields) . ' FROM sig_signatures WHERE user_id = ? ORDER BY ' . 
                   (in_array('updated_at', $columnNames) ? 'updated_at' : 'id') . ' DESC';
            
            error_log('Signatures list SQL: ' . $sql);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $signatures = $stmt->fetchAll();
            
            // Ensure every signature has a public_uuid
            foreach ($signatures as &$signature) {
                if (empty($signature['public_uuid'])) {
                    $signature['public_uuid'] = $signature['id'];
                }
            }
            
            error_log('Signatures found: ' . count($signatures));
            error_log('First signature: ' . json_encode($signatures[0] ?? null));
            
            echo json_encode([
                'success' => true,
                'signatures' => $signatures
            ]);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error']);
            exit;
        }
    }
    
    // CREATE
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['templateKey']) || !isset($input['config'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }
        
        // Check authentication for logged-in users
        $userId = null;
        $isAuthenticated = $auth->isAuthenticated();
        
        if ($isAuthenticated) {
            $currentUser = $auth->getCurrentUser();
            if ($currentUser) {
                $userId = $currentUser['id'];
            }
        }
        
        // If not authenticated, allow guest signature creation with email
        if (!$userId) {
            if (!isset($input['email']) || empty($input['email'])) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Email required for guest signatures']);
                exit;
            }
            
            // For guest users, we'll use email as identifier but not create a user account
            // This maintains the existing guest functionality
            $guestEmail = $input['email'];
        }
        
        try {
            // Create signature in database
            $uuid = bin2hex(random_bytes(16));
            $title = $input['title'] ?? 'Untitled Signature';
            $configJson = json_encode($input['config']);
            
            // First, let's see what columns actually exist
            $stmt = $pdo->query("DESCRIBE sig_signatures");
            $columns = $stmt->fetchAll();
            $columnNames = array_column($columns, 'Field');
            
            error_log('Available columns in sig_signatures: ' . implode(', ', $columnNames));
            
            // Build INSERT based on available columns
            $insertColumns = [];
            $insertValues = [];
            $insertParams = [];
            
            // Always try user_id first
            if (in_array('user_id', $columnNames) && $userId) {
                $insertColumns[] = 'user_id';
                $insertValues[] = '?';
                $insertParams[] = $userId;
            }
            
            // Try template_key (most likely to exist)
            if (in_array('template_key', $columnNames)) {
                $insertColumns[] = 'template_key';
                $insertValues[] = '?';
                $insertParams[] = $input['templateKey'];
            }
            
            // Try title
            if (in_array('title', $columnNames)) {
                $insertColumns[] = 'title';
                $insertValues[] = '?';
                $insertParams[] = $title;
            }
            
            // Try config_json or config
            if (in_array('config_json', $columnNames)) {
                $insertColumns[] = 'config_json';
                $insertValues[] = '?';
                $insertParams[] = $configJson;
            } elseif (in_array('config', $columnNames)) {
                $insertColumns[] = 'config';
                $insertValues[] = '?';
                $insertParams[] = $configJson;
            }
            
            // Try uuid or public_uuid if it exists
            if (in_array('uuid', $columnNames)) {
                $insertColumns[] = 'uuid';
                $insertValues[] = '?';
                $insertParams[] = $uuid;
            } elseif (in_array('public_uuid', $columnNames)) {
                $insertColumns[] = 'public_uuid';
                $insertValues[] = '?';
                $insertParams[] = $uuid;
            }
            
            if (empty($insertColumns)) {
                throw new Exception('No compatible columns found in sig_signatures table');
            }
            
            $sql = 'INSERT INTO sig_signatures (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertValues) . ')';
            error_log('Executing SQL: ' . $sql);
            error_log('With params: ' . json_encode($insertParams));
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertParams);
            
            $signatureId = $pdo->lastInsertId();
            
            // Basic response (simplified - no tracking links for now)
            $result = [
                'id' => $signatureId,
                'uuid' => $uuid,
                'title' => $title,
                'template_key' => $input['templateKey']
            ];
            
            echo json_encode([
                'success' => true,
                'signature' => $result,
                'tracking_links' => [] // Simplified for now - tracking can be added later
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log('Signature creation error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false, 
                'error' => 'Failed to create signature: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ]);
            exit;
        }
    }
    
    // READ
    if ($method === 'GET') {
        // UUID or ID access
        if (isset($_GET['uuid'])) {
            try {
                // Check what columns exist
                $stmt = $pdo->query("DESCRIBE sig_signatures");
                $columns = $stmt->fetchAll();
                $columnNames = array_column($columns, 'Field');
                
                $signature = null;
                $lookupValue = $_GET['uuid'];
                
                // Try UUID columns first if they exist
                if (in_array('public_uuid', $columnNames)) {
                    $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE public_uuid = ?');
                    $stmt->execute([$lookupValue]);
                    $signature = $stmt->fetch();
                } elseif (in_array('uuid', $columnNames)) {
                    $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE uuid = ?');
                    $stmt->execute([$lookupValue]);
                    $signature = $stmt->fetch();
                }
                
                // If not found and lookup value is numeric, try ID
                if (!$signature && is_numeric($lookupValue)) {
                    $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE id = ?');
                    $stmt->execute([$lookupValue]);
                    $signature = $stmt->fetch();
                }
                
                if (!$signature) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Signature not found']);
                    exit;
                }
                
                // Ensure config_json is properly formatted
                if (isset($signature['config']) && !isset($signature['config_json'])) {
                    $signature['config_json'] = $signature['config'];
                }
                
                echo json_encode([
                    'success' => true,
                    'signature' => $signature,
                ]);
                exit;
            } catch (Exception $e) {
                error_log('Signature GET error: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
                exit;
            }
        }
        
        // ID access (requires auth)
        if (isset($_GET['id'])) {
            // Check authentication
            if (!$auth->isAuthenticated()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Not authenticated']);
                exit;
            }
            
            $currentUser = $auth->getCurrentUser();
            if (!$currentUser) {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Invalid session']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE id = ? AND user_id = ?');
                $stmt->execute([$_GET['id'], $currentUser['id']]);
                $signature = $stmt->fetch();
                
                if (!$signature) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Signature not found']);
                    exit;
                }
                
                echo json_encode([
                    'success' => true,
                    'signature' => $signature,
                ]);
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Database error']);
                exit;
            }
        }
        
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Missing id or uuid parameter']);
        exit;
    }
    
    // UPDATE
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($_GET['uuid'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing uuid parameter']);
            exit;
        }
        
        // Check authentication
        if (!$auth->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
        
        $currentUser = $auth->getCurrentUser();
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid session']);
            exit;
        }
        
        $userId = $currentUser['id'];
        $uuid = $_GET['uuid'];
        
        try {
            // Check what columns exist
            $stmt = $pdo->query("DESCRIBE sig_signatures");
            $columns = $stmt->fetchAll();
            $columnNames = array_column($columns, 'Field');
            
            // Find the signature and verify ownership
            $signature = null;
            if (in_array('public_uuid', $columnNames)) {
                $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE public_uuid = ? AND user_id = ?');
                $stmt->execute([$uuid, $userId]);
                $signature = $stmt->fetch();
            } elseif (in_array('uuid', $columnNames)) {
                $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE uuid = ? AND user_id = ?');
                $stmt->execute([$uuid, $userId]);
                $signature = $stmt->fetch();
            } elseif (is_numeric($uuid)) {
                $stmt = $pdo->prepare('SELECT * FROM sig_signatures WHERE id = ? AND user_id = ?');
                $stmt->execute([$uuid, $userId]);
                $signature = $stmt->fetch();
            }
            
            if (!$signature) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Signature not found or access denied']);
                exit;
            }
            
            // Prepare update data
            $updateFields = [];
            $updateParams = [];
            
            if (isset($input['title']) && in_array('title', $columnNames)) {
                $updateFields[] = 'title = ?';
                $updateParams[] = $input['title'];
            }
            
            if (isset($input['config']) && in_array('config_json', $columnNames)) {
                $updateFields[] = 'config_json = ?';
                $updateParams[] = is_string($input['config']) ? $input['config'] : json_encode($input['config']);
            } elseif (isset($input['config']) && in_array('config', $columnNames)) {
                $updateFields[] = 'config = ?';
                $updateParams[] = is_string($input['config']) ? $input['config'] : json_encode($input['config']);
            }
            
            if (in_array('updated_at', $columnNames)) {
                $updateFields[] = 'updated_at = NOW()';
            }
            
            if (empty($updateFields)) {
                echo json_encode(['success' => true, 'message' => 'No updates needed']);
                exit;
            }
            
            // Build and execute update query
            $updateParams[] = $signature['id'];
            $sql = 'UPDATE sig_signatures SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
            
            error_log('Update SQL: ' . $sql);
            error_log('Update params: ' . json_encode($updateParams));
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateParams);
            
            echo json_encode([
                'success' => true,
                'message' => 'Signature updated successfully'
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log('Signature update error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to update signature: ' . $e->getMessage()]);
            exit;
        }
    }
    
    // DELETE (temporarily disabled - needs refactoring)
    if ($method === 'DELETE') {
        http_response_code(501);
        echo json_encode(['success' => false, 'error' => 'Delete functionality temporarily unavailable']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
        'message' => $e->getMessage(), // Show error for debugging
    ]);
}
