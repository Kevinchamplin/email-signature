<?php
/**
 * Simple Database Migration Runner
 */

echo "=== Email Signature Generator - Database Migration ===\n\n";

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
    
    // Read and execute schema
    echo "Running schema migration...\n";
    $schema = file_get_contents(__DIR__ . '/database/schema.sql');
    $pdo->exec($schema);
    echo "✓ Schema created\n\n";
    
    // Read and execute seed
    echo "Running template seed...\n";
    $seed = file_get_contents(__DIR__ . '/database/seed_templates.sql');
    $pdo->exec($seed);
    echo "✓ Templates seeded\n\n";
    
    // Verify
    echo "Verifying installation...\n";
    $count = $pdo->query("SELECT COUNT(*) FROM sig_templates WHERE is_active = 1")->fetchColumn();
    echo "  ✓ sig_templates: $count templates\n";
    
    $count = $pdo->query("SELECT COUNT(*) FROM sig_users")->fetchColumn();
    echo "  ✓ sig_users: $count users\n";
    
    echo "\n✅ Migration Complete!\n\n";
    echo "Next: Visit https://apps.ironcrestsoftware.com/email-signature/public/\n\n";
    
} catch (PDOException $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}
