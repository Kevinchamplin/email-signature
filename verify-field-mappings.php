<?php
/**
 * Field Mapping Verification Script
 * Checks that all fields are correctly mapped across the system
 */

echo "=== EMAIL SIGNATURE GENERATOR - FIELD MAPPING VERIFICATION ===\n\n";

// Define expected field structure
$expectedFields = [
    'identity' => ['name', 'title', 'pronouns'],
    'company' => ['name', 'logoUrl'],
    'contact' => ['email', 'phone', 'website', 'calendly'],
    'links' => ['linkedin', 'twitter', 'github', 'instagram', 'facebook', 'youtube'],
    'branding' => ['primaryColor', 'accentColor', 'spacing', 'cornerRadius', 'logoSize'],
    'addons' => [
        'cta' => ['label', 'url'],
        'disclaimer' => null
    ]
];

// Check HTML form fields
echo "1. Checking HTML Form Fields (app.html)...\n";
$appHtml = file_get_contents(__DIR__ . '/public/app.html');
$formFieldsFound = [];
$formFieldsMissing = [];

foreach ($expectedFields as $category => $fields) {
    if (is_array($fields)) {
        foreach ($fields as $field) {
            if (is_array($field)) {
                // Nested field (like cta)
                foreach ($field as $subfield) {
                    $searchPattern = "name=\"{$category}.{$field}.{$subfield}\"";
                    if (strpos($appHtml, $searchPattern) !== false) {
                        $formFieldsFound[] = "{$category}.{$field}.{$subfield}";
                    } else {
                        $formFieldsMissing[] = "{$category}.{$field}.{$subfield}";
                    }
                }
            } else {
                $searchPattern = "name=\"{$category}.{$field}\"";
                if (strpos($appHtml, $searchPattern) !== false) {
                    $formFieldsFound[] = "{$category}.{$field}";
                } else {
                    $formFieldsMissing[] = "{$category}.{$field}";
                }
            }
        }
    }
}

echo "   ✓ Found: " . count($formFieldsFound) . " fields\n";
if (!empty($formFieldsMissing)) {
    echo "   ✗ Missing: " . implode(', ', $formFieldsMissing) . "\n";
}
echo "\n";

// Check JavaScript state structure
echo "2. Checking JavaScript State (app.js)...\n";
$appJs = file_get_contents(__DIR__ . '/public/js/app.js');
$jsChecks = [
    'state.config.identity' => strpos($appJs, 'identity:') !== false,
    'state.config.company' => strpos($appJs, 'company:') !== false,
    'state.config.contact' => strpos($appJs, 'contact:') !== false,
    'state.config.links' => strpos($appJs, 'links:') !== false,
    'state.config.branding' => strpos($appJs, 'branding:') !== false,
    'state.config.addons' => strpos($appJs, 'addons:') !== false,
];

foreach ($jsChecks as $path => $found) {
    echo ($found ? "   ✓" : "   ✗") . " {$path}\n";
}
echo "\n";

// Check database schema
echo "3. Checking Database Schema...\n";
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/Database.php';

try {
    $db = new Ironcrest\Signature\Database($dbConfig);
    $pdo = $db->getConnection();
    
    // Check sig_user_preferences table
    $stmt = $pdo->query("DESCRIBE sig_user_preferences");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedColumns = [
        'name', 'title', 'pronouns',
        'company_name', 'logo_url',
        'email', 'phone', 'website', 'calendly',
        'social_links', 'branding_preferences', 'addons'
    ];
    
    foreach ($expectedColumns as $col) {
        $found = in_array($col, $columns);
        echo ($found ? "   ✓" : "   ✗") . " Column: {$col}\n";
    }
} catch (Exception $e) {
    echo "   ✗ Database connection failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Check API endpoints
echo "4. Checking API Field Handling (preferences.php)...\n";
$prefsApi = file_get_contents(__DIR__ . '/api/preferences.php');
$apiFields = [
    'name' => strpos($prefsApi, '$name =') !== false,
    'title' => strpos($prefsApi, '$title =') !== false,
    'email' => strpos($prefsApi, '$email =') !== false,
    'phone' => strpos($prefsApi, '$phone =') !== false,
    'website' => strpos($prefsApi, '$website =') !== false,
    'calendly' => strpos($prefsApi, '$calendly =') !== false,
];

foreach ($apiFields as $field => $found) {
    echo ($found ? "   ✓" : "   ✗") . " API handles: {$field}\n";
}
echo "\n";

// Check Renderer
echo "5. Checking Renderer Field Usage (Renderer.php)...\n";
$renderer = file_get_contents(__DIR__ . '/src/Renderer.php');
$rendererChecks = [
    "identity.name" => strpos($renderer, "\$c['identity']['name']") !== false,
    "identity.title" => strpos($renderer, "\$c['identity']['title']") !== false,
    "company.name" => strpos($renderer, "\$c['company']['name']") !== false,
    "contact.email" => strpos($renderer, "\$c['contact']['email']") !== false,
    "contact.phone" => strpos($renderer, "\$c['contact']['phone']") !== false,
    "contact.website" => strpos($renderer, "\$c['contact']['website']") !== false,
    "contact.calendly" => strpos($renderer, "\$c['contact']['calendly']") !== false,
    "addons.disclaimer" => strpos($renderer, "\$c['addons']['disclaimer']") !== false,
    "addons.cta" => strpos($renderer, "\$c['addons']['cta']") !== false,
];

foreach ($rendererChecks as $field => $found) {
    echo ($found ? "   ✓" : "   ✗") . " Renderer uses: {$field}\n";
}
echo "\n";

// Verify field usage is correct
echo "6. Verifying Correct Field Usage...\n";

// Check calendly is NOT used for confidential notice
$calendlyConfidential = strpos($renderer, "CONFIDENTIAL:' . htmlspecialchars(\$c['contact']['calendly']");
if ($calendlyConfidential === false) {
    echo "   ✓ Calendly is NOT used for confidential notice\n";
} else {
    echo "   ✗ ERROR: Calendly is still used for confidential notice!\n";
}

// Check calendly IS used as a link
$calendlyLink = strpos($renderer, "Book a Meeting");
if ($calendlyLink !== false) {
    echo "   ✓ Calendly is used as booking link\n";
} else {
    echo "   ✗ WARNING: Calendly booking link text not found\n";
}

// Check disclaimer is used at bottom
$disclaimerBottom = strpos($renderer, "\$c['addons']['disclaimer']");
if ($disclaimerBottom !== false) {
    echo "   ✓ Disclaimer field exists\n";
} else {
    echo "   ✗ ERROR: Disclaimer field not found!\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
echo "\nSee FIELD-MAPPING.md for complete field documentation.\n";
