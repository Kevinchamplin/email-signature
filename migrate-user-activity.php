<?php
/**
 * User Activity Tracking Migration
 * Run this file once to create user activity tables
 */

try {
    // Direct database connection
    $pdo = new PDO(
        'mysql:host=815hosting.com;dbname=ironcrest_db;charset=utf8mb4',
        'dbuser_ic',
        'jg*ce4%0qgXAoMc3',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "🚀 Starting user activity tracking migration...\n\n";
    
    // Read SQL file
    $sql = file_get_contents(__DIR__ . '/database/user_activity_schema.sql');
    
    // Remove comments
    $sql = preg_replace('/^--.*$/m', '', $sql);
    
    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && strlen(trim($stmt)) > 10;
        }
    );
    
    $success = 0;
    $failed = 0;
    
    foreach ($statements as $statement) {
        if (empty(trim($statement))) continue;
        
        try {
            // Extract table name for display
            preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches);
            $tableName = $matches[1] ?? 'unknown';
            
            $pdo->exec($statement);
            echo "✅ Created table: {$tableName}\n";
            $success++;
        } catch (Exception $e) {
            echo "❌ Error creating table: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
    
    echo "\n";
    echo "📊 Migration Summary:\n";
    echo "   ✅ Success: {$success} tables\n";
    echo "   ❌ Failed: {$failed} tables\n";
    echo "\n";
    
    if ($failed === 0) {
        echo "🎉 User activity tracking migration completed successfully!\n";
    } else {
        echo "⚠️  Some tables failed to create. Check errors above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
