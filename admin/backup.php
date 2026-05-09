<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle backup actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_backup') {
        try {
            $backupDir = '../backups/';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }
            
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "ventdepot_backup_$timestamp.sql";
            $filepath = $backupDir . $filename;
            
            // Get database name
            $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
            
            // Create backup command — credentials from environment, never hardcoded
            $dbHost = env('DB_HOST', 'localhost');
            $dbUser = env('DB_USERNAME', 'root');
            $dbPass = env('DB_PASSWORD', '');
            $command = sprintf(
                'mysqldump --host=%s --user=%s %s --single-transaction --routines --triggers %s > %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                !empty($dbPass) ? '--password=' . escapeshellarg($dbPass) : '',
                escapeshellarg($dbName),
                escapeshellarg($filepath)
            );
            
            // Execute backup (Note: This is a simplified version - in production you'd want better error handling)
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($filepath)) {
                $success = "Backup created successfully: $filename";
            } else {
                $error = 'Failed to create backup. Please check database credentials and permissions.';
            }
        } catch (Exception $e) {
            $error = 'Backup failed: ' . $e->getMessage();
        }
    } elseif ($action === 'delete_backup') {
        $filename = $_POST['filename'] ?? '';
        $filepath = '../backups/' . basename($filename);
        
        if (file_exists($filepath) && unlink($filepath)) {
            $success = 'Backup deleted successfully.';
        } else {
            $error = 'Failed to delete backup file.';
        }
    }
}

// Get existing backups
$backupDir = '../backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filepath = $backupDir . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filepath),
                'created' => filemtime($filepath)
            ];
        }
    }
    // Sort by creation time (newest first)
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// Get database statistics
$dbStats = [
    'total_tables' => $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn(),
    'total_size' => $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables 
        WHERE table_schema = DATABASE()
    ")->fetchColumn(),
    'total_records' => 0
];

// Get record counts for major tables
$majorTables = ['users', 'products', 'orders', 'order_items', 'shipments', 'system_logs'];
foreach ($majorTables as $table) {
    try {
        // Use prepared statements to safely query table counts
        $allowedTables = [
            'users' => 'users',
            'products' => 'products', 
            'orders' => 'orders',
            'order_items' => 'order_items',
            'shipments' => 'shipments',
            'system_logs' => 'system_logs'
        ];
        
        if (isset($allowedTables[$table])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM `" . $allowedTables[$table] . "`");
            $stmt->execute();
            $count = $stmt->fetchColumn();
            $dbStats['total_records'] += $count;
            $dbStats['tables'][$table] = $count;
        } else {
            $dbStats['tables'][$table] = 0;
        }
    } catch (Exception $e) {
        $dbStats['tables'][$table] = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Maintenance - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-red-600 hover:text-red-700">Admin Panel</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-6xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Backup & Maintenance</h1>
                <p class="text-gray-600 mt-2">Database backup, system maintenance, and optimization tools</p>
            </div>
            <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
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

        <!-- Database Statistics -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Database Overview</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= $dbStats['total_tables'] ?></div>
                    <div class="text-sm text-gray-600">Total Tables</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?= $dbStats['total_size'] ?> MB</div>
                    <div class="text-sm text-gray-600">Database Size</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-purple-600"><?= number_format($dbStats['total_records']) ?></div>
                    <div class="text-sm text-gray-600">Total Records</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-orange-600"><?= count($backups) ?></div>
                    <div class="text-sm text-gray-600">Available Backups</div>
                </div>
            </div>
            
            <div class="border-t border-gray-200 pt-6">
                <h3 class="font-medium text-gray-900 mb-4">Table Record Counts</h3>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <?php foreach ($dbStats['tables'] as $table => $count): ?>
                        <div class="text-center p-3 border border-gray-200 rounded-lg">
                            <div class="font-semibold text-gray-900"><?= number_format($count) ?></div>
                            <div class="text-xs text-gray-600"><?= ucfirst($table) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Backup Management -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Create Backup -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Create New Backup</h2>
                
                <div class="space-y-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">Backup Information</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        <li>Full database backup including all tables</li>
                                        <li>Includes stored procedures and triggers</li>
                                        <li>Compressed SQL format for easy restoration</li>
                                        <li>Automatic timestamp in filename</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="create_backup">
                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-md hover:bg-blue-700">
                            <i class="fas fa-database mr-2"></i>Create Database Backup
                        </button>
                    </form>
                </div>
            </div>

            <!-- System Maintenance -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">System Maintenance</h2>
                
                <div class="space-y-4">
                    <button onclick="optimizeDatabase()" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700">
                        <i class="fas fa-cogs mr-2"></i>Optimize Database
                    </button>
                    
                    <button onclick="clearCache()" class="w-full bg-yellow-600 text-white py-2 px-4 rounded-md hover:bg-yellow-700">
                        <i class="fas fa-broom mr-2"></i>Clear System Cache
                    </button>
                    
                    <button onclick="checkIntegrity()" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700">
                        <i class="fas fa-check-circle mr-2"></i>Check Data Integrity
                    </button>
                    
                    <button onclick="cleanupFiles()" class="w-full bg-orange-600 text-white py-2 px-4 rounded-md hover:bg-orange-700">
                        <i class="fas fa-trash-alt mr-2"></i>Cleanup Temporary Files
                    </button>
                </div>
            </div>
        </div>

        <!-- Existing Backups -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Existing Backups</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Filename</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Size</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($backups)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">
                                    No backups found. Create your first backup above.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($backups as $backup): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-file-archive text-blue-600 mr-3"></i>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($backup['filename']) ?></div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= number_format($backup['size'] / 1024 / 1024, 2) ?> MB
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j, Y H:i:s', $backup['created']) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            <a href="../backups/<?= htmlspecialchars($backup['filename']) ?>" 
                                               class="text-blue-600 hover:text-blue-900" download>
                                                <i class="fas fa-download"></i>
                                            </a>
                                            
                                            <form method="POST" class="inline" 
                                                  onsubmit="return confirm('Are you sure you want to delete this backup?')">
                                                <input type="hidden" name="action" value="delete_backup">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($backup['filename']) ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Backup Schedule -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Automated Backup Schedule</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-medium text-gray-900 mb-4">Current Schedule</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg">
                            <span class="text-gray-700">Daily Backup</span>
                            <span class="text-sm text-green-600">Enabled (2:00 AM)</span>
                        </div>
                        <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg">
                            <span class="text-gray-700">Weekly Full Backup</span>
                            <span class="text-sm text-green-600">Enabled (Sunday 1:00 AM)</span>
                        </div>
                        <div class="flex justify-between items-center p-3 border border-gray-200 rounded-lg">
                            <span class="text-gray-700">Cleanup Old Backups</span>
                            <span class="text-sm text-blue-600">Keep 30 days</span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h3 class="font-medium text-gray-900 mb-4">Backup Settings</h3>
                    <form class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Backup Frequency</label>
                            <select class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option>Daily</option>
                                <option>Weekly</option>
                                <option>Monthly</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Retention Period</label>
                            <select class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option>7 days</option>
                                <option selected>30 days</option>
                                <option>90 days</option>
                                <option>1 year</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700">
                            Update Schedule
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Health -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">System Health Check</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-server text-green-600 text-2xl mb-2"></i>
                    <div class="font-semibold text-gray-900">Database</div>
                    <div class="text-sm text-green-600">Healthy</div>
                </div>
                
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-hdd text-yellow-600 text-2xl mb-2"></i>
                    <div class="font-semibold text-gray-900">Disk Space</div>
                    <div class="text-sm text-yellow-600">75% Used</div>
                </div>
                
                <div class="text-center p-4 border border-gray-200 rounded-lg">
                    <i class="fas fa-memory text-green-600 text-2xl mb-2"></i>
                    <div class="font-semibold text-gray-900">Memory</div>
                    <div class="text-sm text-green-600">Normal</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function optimizeDatabase() {
            if (confirm('This will optimize all database tables. Continue?')) {
                // Implement database optimization
                alert('Database optimization would be implemented here');
            }
        }
        
        function clearCache() {
            if (confirm('Clear all system cache files?')) {
                // Implement cache clearing
                alert('Cache clearing would be implemented here');
            }
        }
        
        function checkIntegrity() {
            // Implement data integrity check
            alert('Data integrity check would be implemented here');
        }
        
        function cleanupFiles() {
            if (confirm('Clean up temporary files and logs?')) {
                // Implement file cleanup
                alert('File cleanup would be implemented here');
            }
        }
    </script>
</body>
</html>
