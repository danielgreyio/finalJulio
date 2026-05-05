<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_settings') {
        $settings = $_POST['settings'] ?? [];
        
        try {
            $pdo->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $stmt = $pdo->prepare("
                    INSERT INTO site_settings (setting_key, setting_value) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
                ");
                $stmt->execute([$key, $value]);
            }
            
            $pdo->commit();
            $success = 'Settings updated successfully!';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Failed to update settings: ' . $e->getMessage();
        }
    }
}

// Get all settings
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value, setting_type, description FROM site_settings ORDER BY setting_key");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row;
}

// Group settings by category
$settingsGroups = [
    'Site Configuration' => [
        'site_name', 'site_tagline', 'maintenance_mode', 'allow_guest_checkout'
    ],
    'Security Settings' => [
        'require_email_verification', 'max_login_attempts', 'session_timeout'
    ],
    'Contact Information' => [
        'contact_phone', 'contact_email', 'support_email', 'sales_email', 'contact_address', 'business_hours'
    ],
    'Shipping Configuration' => [
        'shipping_domestic_info', 'shipping_international_info', 'shipping_processing_time', 
        'shipping_rates_info', 'shipping_restrictions', 'shipping_tracking_info'
    ],
    'Returns & Refunds' => [
        'returns_policy', 'refund_policy', 'exchange_policy', 'return_process'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Sidebar Backdrop -->
    <div x-data="{ sidebarOpen: false }" class="relative z-0 flex-1 flex flex-col overflow-hidden">
        <!-- Mobile Header -->
        <div class="md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-white border-b border-gray-200">
            <button @click="sidebarOpen = !sidebarOpen" class="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                <span class="sr-only">Open sidebar</span>
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Main Content -->
        <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none">
            <div class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Site Settings</h1>
                            <p class="text-gray-600 mt-2">Configure global site settings and preferences</p>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-8">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <?php foreach ($settingsGroups as $groupName => $groupKeys): ?>
                            <div class="bg-white rounded-lg shadow-md p-6">
                                <h2 class="text-xl font-semibold text-gray-900 mb-6"><?= $groupName ?></h2>
                                
                                <div class="space-y-6">
                                    <?php foreach ($groupKeys as $key): ?>
                                        <?php if (isset($settings[$key])): ?>
                                            <?php $setting = $settings[$key]; ?>
                                            <div>
                                                <label for="<?= $key ?>" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <?= ucwords(str_replace('_', ' ', $key)) ?>
                                                    <?php if ($setting['description']): ?>
                                                        <span class="text-gray-500 font-normal">- <?= htmlspecialchars($setting['description']) ?></span>
                                                    <?php endif; ?>
                                                </label>
                                                
                                                <?php if ($setting['setting_type'] === 'textarea'): ?>
                                                    <textarea name="settings[<?= $key ?>]" id="<?= $key ?>" rows="4"
                                                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($setting['setting_value']) ?></textarea>
                                                <?php elseif ($setting['setting_type'] === 'boolean'): ?>
                                                    <div class="flex items-center">
                                                        <input type="hidden" name="settings[<?= $key ?>]" value="0">
                                                        <input type="checkbox" name="settings[<?= $key ?>]" id="<?= $key ?>" value="1"
                                                               <?= $setting['setting_value'] ? 'checked' : '' ?>
                                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                        <label for="<?= $key ?>" class="ml-2 text-sm text-gray-700">
                                                            Enable this setting
                                                        </label>
                                                    </div>
                                                <?php elseif ($setting['setting_type'] === 'number'): ?>
                                                    <input type="number" name="settings[<?= $key ?>]" id="<?= $key ?>"
                                                           value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                                <?php else: ?>
                                                    <input type="text" name="settings[<?= $key ?>]" id="<?= $key ?>"
                                                           value="<?= htmlspecialchars($setting['setting_value']) ?>"
                                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Advanced Settings -->
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h2 class="text-xl font-semibold text-gray-900 mb-6">Advanced Settings</h2>
                            
                            <div class="space-y-6">
                                <!-- Add New Setting -->
                                <div class="border-t border-gray-200 pt-6">
                                    <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Setting</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4" x-data="{ newSetting: { key: '', value: '', type: 'text' } }">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Setting Key</label>
                                            <input type="text" x-model="newSetting.key" placeholder="setting_key"
                                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Setting Type</label>
                                            <select x-model="newSetting.type"
                                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                                <option value="text">Text</option>
                                                <option value="textarea">Textarea</option>
                                                <option value="number">Number</option>
                                                <option value="boolean">Boolean</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Setting Value</label>
                                            <input type="text" x-model="newSetting.value" placeholder="Setting value"
                                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <button type="button" onclick="addNewSetting()" 
                                                class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                            <i class="fas fa-plus mr-2"></i>Add Setting
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded-md hover:bg-blue-700">
                                <i class="fas fa-save mr-2"></i>Save All Settings
                            </button>
                        </div>
                    </form>

                    <!-- System Information -->
                    <div class="bg-white rounded-lg shadow-md p-6 mt-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">System Information</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4">Server Information</h3>
                                <dl class="space-y-2">
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">PHP Version:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= PHP_VERSION ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Server Software:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Memory Limit:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= ini_get('memory_limit') ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Upload Max Size:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= ini_get('upload_max_filesize') ?></dd>
                                    </div>
                                </dl>
                            </div>
                            
                            <div>
                                <h3 class="font-medium text-gray-900 mb-4">Database Information</h3>
                                <dl class="space-y-2">
                                    <?php
                                    $dbInfo = $pdo->query("SELECT VERSION() as version")->fetch();
                                    $tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
                                    ?>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">MySQL Version:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= $dbInfo['version'] ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Database Name:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= $pdo->query("SELECT DATABASE()")->fetchColumn() ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Total Tables:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= $tableCount ?></dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600">Settings Count:</dt>
                                        <dd class="text-sm font-medium text-gray-900"><?= count($settings) ?></dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6 mt-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-6">Quick Actions</h2>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <button onclick="clearCache()" class="bg-yellow-600 text-white px-4 py-2 rounded-md hover:bg-yellow-700">
                                <i class="fas fa-broom mr-2"></i>Clear Cache
                            </button>
                            
                            <button onclick="exportSettings()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-download mr-2"></i>Export Settings
                            </button>
                            
                            <button onclick="resetToDefaults()" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700"
                                    onclick="return confirm('Are you sure you want to reset all settings to defaults?')">
                                <i class="fas fa-undo mr-2"></i>Reset to Defaults
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function addNewSetting() {
            // This would be implemented to dynamically add new settings
            alert('Add new setting functionality would be implemented here');
        }
        
        function clearCache() {
            // Implement cache clearing
            alert('Cache clearing functionality would be implemented here');
        }
        
        function exportSettings() {
            // Implement settings export
            window.location.href = 'export-settings.php';
        }
        
        function resetToDefaults() {
            if (confirm('Are you sure you want to reset all settings to defaults? This cannot be undone.')) {
                // Implement reset functionality
                alert('Reset functionality would be implemented here');
            }
        }
    </script>
</body>
</html>
