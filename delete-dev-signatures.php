<?php
/**
 * Delete all development signatures
 * Run this once to clean up test data
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
    
    echo "🗑️  Deleting all development signatures...\n\n";
    
    // Get count before deletion
    $countBefore = $pdo->query("SELECT COUNT(*) as count FROM sig_signatures")->fetch()['count'];
    echo "Found {$countBefore} signatures\n";
    
    // Delete all signatures
    $pdo->exec("DELETE FROM sig_signatures");
    
    // Reset auto-increment
    $pdo->exec("ALTER TABLE sig_signatures AUTO_INCREMENT = 1");
    
    // Verify deletion
    $countAfter = $pdo->query("SELECT COUNT(*) as count FROM sig_signatures")->fetch()['count'];
    
    echo "\n✅ Deleted {$countBefore} signatures\n";
    echo "✅ Remaining signatures: {$countAfter}\n";
    echo "✅ Auto-increment reset to 1\n";
    echo "\n🎉 Database cleaned successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
