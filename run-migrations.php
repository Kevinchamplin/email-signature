<?php
/**
 * Database Migration Runner
 * Run this file to set up the database tables
 */

echo "=== Email Signature Generator - Database Migration ===\n\n";

// Load config
$config = require __DIR__ . '/config/config.php';

// Connect to database
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
    
} catch (PDOException $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Run schema migration
echo "Running schema.sql...\n";
$schema = file_get_contents(__DIR__ . '/database/schema.sql');

// Split by semicolons and execute each statement
$statements = array_filter(
    array_map('trim', explode(';', $schema)),
    function($stmt) {
        return !empty($stmt) && 
               !preg_match('/^--/', $stmt) && 
               !preg_match('/^\/\*/', $stmt);
    }
);

$successCount = 0;
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        $successCount++;
    } catch (PDOException $e) {
        // Ignore "table already exists" errors
        if (strpos($e->getMessage(), 'already exists') === false) {
            echo "  Warning: " . $e->getMessage() . "\n";
        }
    }
}

echo "✓ Schema migration complete ($successCount statements)\n\n";

// Run template seed
echo "Running seed_templates.sql...\n";
$seed = file_get_contents(__DIR__ . '/database/seed_templates.sql');

$statements = array_filter(
    array_map('trim', explode(';', $seed)),
    function($stmt) {
        return !empty($stmt) && 
               !preg_match('/^--/', $stmt) && 
               !preg_match('/^\/\*/', $stmt);
    }
);

$successCount = 0;
foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        $successCount++;
    } catch (PDOException $e) {
        echo "  Warning: " . $e->getMessage() . "\n";
    }
}

echo "✓ Template seed complete ($successCount statements)\n\n";

// Verify installation
echo "Verifying installation...\n";

$tables = [
    'sig_users',
    'sig_signatures',
    'sig_templates',
    'sig_events',
    'sig_magic_links',
    'sig_email_verifications',
];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "  ✓ $table: $count rows\n";
    } catch (PDOException $e) {
        echo "  ✗ $table: NOT FOUND\n";
    }
}

echo "\n=== Migration Complete! ===\n";
echo "\nNext steps:\n";
echo "1. Visit: https://apps.ironcrestsoftware.com/email-signature/public/\n";
echo "2. Test the signature generator\n";
echo "3. Delete this file: run-migrations.php\n\n";
