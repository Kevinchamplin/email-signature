<?php
/**
 * Add company_slogan column to sig_user_preferences
 */

try {
    $pdo = new PDO(
        'mysql:host=815hosting.com;dbname=ironcrest_db;charset=utf8mb4',
        'dbuser_ic',
        'jg*ce4%0qgXAoMc3',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Adding company_slogan column...\n";
    
    $pdo->exec("ALTER TABLE sig_user_preferences ADD COLUMN company_slogan VARCHAR(255) DEFAULT NULL AFTER company_name");
    
    echo "✅ Column added successfully!\n";
    
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✅ Column already exists!\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
