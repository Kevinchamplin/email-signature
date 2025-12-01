<?php
/**
 * Template Seeder
 * Runs the seed_templates.sql file
 */

$config = require __DIR__ . '/config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $config['database']['host'] . ';dbname=' . $config['database']['name'],
        $config['database']['username'],
        $config['database']['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
    
    echo "=== Running Template Seeder ===\n\n";
    
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/database/seed_templates.sql');
    
    // Remove comments
    $lines = explode("\n", $sql);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }
        $cleanedLines[] = $line;
    }
    $sql = implode("\n", $cleanedLines);
    
    // Split by semicolon to get individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement)) continue;
        
        try {
            $pdo->exec($statement);
            
            // Check if it's an INSERT statement
            if (stripos($statement, 'INSERT INTO') === 0) {
                // Extract template name
                if (preg_match("/VALUES\s*\([^,]+,\s*'([^']+)/", $statement, $matches)) {
                    echo "✓ Inserted template: " . $matches[1] . "\n";
                } else {
                    echo "✓ Executed INSERT statement\n";
                }
                $successCount++;
            }
        } catch (Exception $e) {
            // Check if it's a duplicate entry error
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (preg_match("/VALUES\s*\([^,]+,\s*'([^']+)/", $statement, $matches)) {
                    echo "⊙ Template already exists: " . $matches[1] . "\n";
                }
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    // Verify final count
    $count = $pdo->query('SELECT COUNT(*) FROM sig_templates WHERE is_active = 1')->fetchColumn();
    echo "\n=== Summary ===\n";
    echo "Total active templates in database: $count\n";
    echo "New templates inserted: $successCount\n";
    if ($errorCount > 0) {
        echo "Errors: $errorCount\n";
    }
    
    // List all templates
    echo "\n=== All Templates ===\n";
    $templates = $pdo->query('SELECT template_key, name, sort_order FROM sig_templates WHERE is_active = 1 ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($templates as $t) {
        echo sprintf("%2d. %-30s (%s)\n", $t['sort_order'], $t['name'], $t['template_key']);
    }
    
    echo "\n✅ Template seeding complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
