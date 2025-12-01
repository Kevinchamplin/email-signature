<?php
/**
 * Templates API
 * GET /api/templates.php - List all active templates
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../vendor/autoload.php';

use Ironcrest\Signature\Database;

try {
    $config = require __DIR__ . '/../config/config.php';
    $db = Database::getInstance($config['database']);
    
    // Get all active templates
    $sql = 'SELECT id, template_key, name, description, thumbnail_url, meta_json, default_json, sort_order 
            FROM sig_templates 
            WHERE is_active = 1 
            ORDER BY sort_order ASC';
    
    $templates = $db->fetchAll($sql);
    
    // Define free templates (first 3)
    $freeTemplates = ['minimal-line', 'corporate-block', 'badge'];
    
    // Decode JSON fields and add tier info
    foreach ($templates as &$template) {
        $template['meta_json'] = json_decode($template['meta_json'], true);
        $template['default_json'] = json_decode($template['default_json'], true);
        $template['is_premium'] = !in_array($template['template_key'], $freeTemplates);
        $template['requires_login'] = $template['is_premium'];
    }
    
    echo json_encode([
        'success' => true,
        'templates' => $templates,
        'count' => count($templates),
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch templates',
        'message' => $config['app']['debug'] ? $e->getMessage() : 'Internal server error',
    ]);
}
