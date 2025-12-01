<?php
/**
 * Beta Control Panel
 * Simple admin page to toggle beta status
 * 
 * SECURITY: Add authentication before using in production!
 */

$configFile = __DIR__ . '/../config/beta.php';
$config = require $configFile;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_beta') {
        $newStatus = !$config['BETA_ACTIVE'];
        
        // Update config file
        $newConfig = "<?php\n/**\n * Beta Configuration\n * Control grandfathered access and beta status\n * \n * IMPORTANT: Set BETA_ACTIVE to false when launching paid tiers\n */\n\nreturn [\n    // Is beta active? (Grandfathered access enabled)\n    'BETA_ACTIVE' => " . ($newStatus ? 'true' : 'false') . ",\n    \n    // Beta end date (optional - for display purposes)\n    'BETA_END_DATE' => " . ($config['BETA_END_DATE'] ? "'{$config['BETA_END_DATE']}'" : 'null') . ",\n    \n    // Message to show when beta is active\n    'BETA_MESSAGE' => '{$config['BETA_MESSAGE']}',\n    \n    // Message to show when beta ends\n    'POST_BETA_MESSAGE' => '{$config['POST_BETA_MESSAGE']}',\n    \n    // Automatically set is_grandfathered=1 for new signups?\n    'AUTO_GRANDFATHER' => " . ($newStatus ? 'true' : 'false') . ",\n];\n";
        
        file_put_contents($configFile, $newConfig);
        $config = require $configFile;
        $message = $newStatus ? "✅ Beta activated! New users will be grandfathered." : "⚠️ Beta ended! New users will NOT be grandfathered.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beta Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-xl shadow-lg p-8">
            <h1 class="text-3xl font-bold mb-2">🎛️ Beta Control Panel</h1>
            <p class="text-gray-600 mb-8">Control grandfathered access for new users</p>
            
            <?php if (isset($message)): ?>
                <div class="mb-6 p-4 rounded-lg <?= $config['BETA_ACTIVE'] ? 'bg-green-50 border-2 border-green-200' : 'bg-yellow-50 border-2 border-yellow-200' ?>">
                    <p class="font-semibold"><?= $message ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Current Status -->
            <div class="mb-8 p-6 rounded-lg <?= $config['BETA_ACTIVE'] ? 'bg-green-50 border-2 border-green-300' : 'bg-red-50 border-2 border-red-300' ?>">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-bold mb-1">Beta Status</h2>
                        <p class="text-sm text-gray-600">New users will <?= $config['AUTO_GRANDFATHER'] ? 'be' : 'NOT be' ?> grandfathered</p>
                    </div>
                    <div class="text-4xl">
                        <?php if ($config['BETA_ACTIVE']): ?>
                            <span class="text-green-600">✅ ACTIVE</span>
                        <?php else: ?>
                            <span class="text-red-600">❌ ENDED</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 mb-8">
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600">Auto Grandfather</div>
                    <div class="text-2xl font-bold"><?= $config['AUTO_GRANDFATHER'] ? 'ON' : 'OFF' ?></div>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <div class="text-sm text-gray-600">Beta Active</div>
                    <div class="text-2xl font-bold"><?= $config['BETA_ACTIVE'] ? 'YES' : 'NO' ?></div>
                </div>
            </div>
            
            <!-- Toggle Button -->
            <form method="POST">
                <input type="hidden" name="action" value="toggle_beta">
                <button type="submit" class="w-full py-4 px-6 rounded-lg font-bold text-lg transition-all <?= $config['BETA_ACTIVE'] ? 'bg-red-600 hover:bg-red-700 text-white' : 'bg-green-600 hover:bg-green-700 text-white' ?>">
                    <?php if ($config['BETA_ACTIVE']): ?>
                        <i class="fas fa-stop-circle mr-2"></i> End Beta (Stop Grandfathering New Users)
                    <?php else: ?>
                        <i class="fas fa-play-circle mr-2"></i> Restart Beta (Resume Grandfathering)
                    <?php endif; ?>
                </button>
            </form>
            
            <div class="mt-6 p-4 bg-yellow-50 border-2 border-yellow-200 rounded-lg">
                <p class="text-sm text-yellow-800">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Warning:</strong> Ending beta will stop new users from getting grandfathered status. Existing grandfathered users keep their access.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
