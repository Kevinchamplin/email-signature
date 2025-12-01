<?php
/**
 * Authentication System Migration
 * Run this to add authentication tables and features
 */

echo "=== Email Signature Generator - Authentication Migration ===\n\n";

$config = require __DIR__ . '/config/config.php';

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['database']['host'],
        $config['database']['name'],
        $config['database']['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $config['database']['username'],
        $config['database']['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "✓ Connected to database: {$config['database']['name']}\n\n";
    
    // Read and execute auth schema
    echo "Running authentication schema migration...\n";
    $schema = file_get_contents(__DIR__ . '/database/auth_schema.sql');
    
    // Remove comments and split by semicolon
    $lines = explode("\n", $schema);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        $cleanedLines[] = $line;
    }
    $cleanedSchema = implode("\n", $cleanedLines);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $cleanedSchema)));
    
    $executed = 0;
    $skipped = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo ".";
            $executed++;
        } catch (PDOException $e) {
            // Ignore "already exists" errors
            if (strpos($e->getMessage(), 'Duplicate column') !== false || 
                strpos($e->getMessage(), 'already exists') !== false ||
                strpos($e->getMessage(), 'Duplicate key') !== false) {
                echo "s"; // skipped
                $skipped++;
            } else {
                echo "\n✗ Error executing statement: " . substr($statement, 0, 100) . "...\n";
                echo "   Error: " . $e->getMessage() . "\n";
                throw $e;
            }
        }
    }
    
    echo "\n✓ Authentication schema migrated ($executed executed, $skipped skipped)\n\n";
    
    // Verify
    echo "Verifying installation...\n";
    
    $tables = ['sig_sessions', 'sig_password_resets', 'sig_feature_access'];
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "  ✓ $table: $count records\n";
    }
    
    // Check columns
    $result = $pdo->query("SHOW COLUMNS FROM sig_users LIKE 'password_hash'")->fetch();
    if ($result) {
        echo "  ✓ sig_users: authentication columns added\n";
    }
    
    echo "\n✅ Authentication Migration Complete!\n\n";
    echo "Features:\n";
    echo "  • User registration and login\n";
    echo "  • Session management (30-day sessions)\n";
    echo "  • Feature gating system\n";
    echo "  • Grandfathered status for early users\n";
    echo "  • Password reset functionality\n\n";
    echo "Next: Update your app to use authentication\n\n";
    
} catch (PDOException $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}
