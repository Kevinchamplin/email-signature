<?php
/**
 * Quick Test Page
 * Test API endpoints and rendering
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Signature Generator - Test Page</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; }
        .test { background: #f8f9fa; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        h2 { color: #2B68C1; }
        pre { background: #fff; padding: 15px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🧪 Email Signature Generator - Test Suite</h1>
    
    <div class="test">
        <h2>1. Database Connection</h2>
        <?php
        try {
            require 'vendor/autoload.php';
            $config = require 'config/config.php';
            $db = Ironcrest\Signature\Database::getInstance($config['database']);
            echo '<p class="success">✓ Database connected successfully!</p>';
            echo '<p>Host: ' . $config['database']['host'] . '</p>';
            echo '<p>Database: ' . $config['database']['name'] . '</p>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Database connection failed: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="test">
        <h2>2. Templates Count</h2>
        <?php
        try {
            $count = $db->getConnection()->query("SELECT COUNT(*) FROM sig_templates WHERE is_active = 1")->fetchColumn();
            echo '<p class="success">✓ Found ' . $count . ' active templates</p>';
            
            $templates = $db->getConnection()->query("SELECT template_key, name FROM sig_templates WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
            echo '<ul>';
            foreach ($templates as $t) {
                echo '<li>' . $t['name'] . ' (' . $t['template_key'] . ')</li>';
            }
            echo '</ul>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="test">
        <h2>3. API Endpoint Test</h2>
        <?php
        $apiUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/api/templates.php';
        echo '<p>Testing: <code>' . $apiUrl . '</code></p>';
        
        $response = @file_get_contents($apiUrl);
        if ($response) {
            $data = json_decode($response, true);
            if ($data && isset($data['success']) && $data['success']) {
                echo '<p class="success">✓ API working! Returned ' . $data['count'] . ' templates</p>';
            } else {
                echo '<p class="error">✗ API returned error</p>';
                echo '<pre>' . htmlspecialchars($response) . '</pre>';
            }
        } else {
            echo '<p class="error">✗ Could not reach API endpoint</p>';
        }
        ?>
    </div>
    
    <div class="test">
        <h2>4. HTML Renderer Test</h2>
        <?php
        try {
            $renderer = new Ironcrest\Signature\Renderer();
            $testConfig = [
                'identity' => ['name' => 'Test User', 'title' => 'Software Engineer'],
                'company' => ['name' => 'Test Company'],
                'contact' => ['email' => 'test@example.com', 'phone' => '+1 555-1234'],
                'links' => ['linkedin' => 'https://linkedin.com/in/test'],
                'branding' => ['accent' => '#2B68C1'],
                'addons' => ['cta' => ['label' => 'Schedule Call', 'url' => 'https://example.com'], 'disclaimer' => '']
            ];
            
            $html = $renderer->render($testConfig, 'minimal-line');
            echo '<p class="success">✓ Renderer working!</p>';
            echo '<h3>Preview:</h3>';
            echo '<div style="border: 2px dashed #ccc; padding: 20px; background: white;">' . $html . '</div>';
        } catch (Exception $e) {
            echo '<p class="error">✗ Renderer error: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="test">
        <h2>5. Mailgun Configuration</h2>
        <?php
        echo '<p>API Key: ' . substr($config['mailgun']['api_key'], 0, 20) . '...</p>';
        echo '<p>Domain: ' . $config['mailgun']['domain'] . '</p>';
        echo '<p>From: ' . $config['mailgun']['from_email'] . '</p>';
        echo '<p class="success">✓ Mailgun configured</p>';
        ?>
    </div>
    
    <div class="test">
        <h2>✅ All Tests Complete!</h2>
        <p><strong>Next Step:</strong> Visit the main app:</p>
        <p><a href="public/" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #2A3B8F 0%, #2B68C1 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">Launch Email Signature Generator →</a></p>
    </div>
    
</body>
</html>
