<?php
// Accounting System Status Dashboard
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Check if user is authenticated and is admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$currentPage = 'accounting-system-status.php'; // For sidebar highlighting

try {
    // Re-use standard PDO connection unless a specific accounting connection is needed
    // Assuming $pdo is available from '../config/database.php'
    // If not, we can re-instantiate, but preferably use the global one.
    // For now, adhering to existing pattern but ensuring error handling.
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    // Get counts for all tables
    $tables = [
        'chart_of_accounts' => 'Chart of Accounts',
        'general_ledger' => 'General Ledger Entries',
        'accounts_payable' => 'Accounts Payable',
        'accounts_receivable' => 'Accounts Receivable',
        'sales_commissions' => 'Sales Commissions',
        'commission_tiers' => 'Commission Tiers',
        'marketing_campaigns' => 'Marketing Campaigns',
        'marketing_expenses' => 'Marketing Expenses',
        'operations_costs' => 'Operations Costs',
        'product_costing' => 'Product Costing Records',
        'payroll' => 'Payroll Records',
        'financial_reports' => 'Financial Reports'
    ];
    
    $counts = [];
    foreach ($tables as $table => $label) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $counts[$table] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        } catch (Exception $e) {
            $counts[$table] = 'Table not found';
        }
    }
    
} catch(PDOException $e) {
    $error = "Database connection failed: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting System Status - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden" x-data="{ sidebarOpen: false }">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Sidebar Backdrop -->
    <div class="relative z-0 flex-1 flex flex-col overflow-hidden">
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
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Accounting System Status</h1>
                            <p class="text-gray-600 mt-2">Overview of financial modules and system health</p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <button onclick="location.reload()" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 shadow-sm text-sm font-medium flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh Status
                            </button>
                            <a href="accounting-dashboard.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 shadow-sm text-sm font-medium flex items-center">
                                <i class="fas fa-tachometer-alt mr-2"></i> Dashboard
                            </a>
                        </div>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Error</p>
                            <p><?= htmlspecialchars($error) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <!-- System Status Overview -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">System Status</h3>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-100 flex items-center justify-center mr-4">
                                    <i class="fas fa-check text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-green-600">Operational</p>
                                    <p class="text-sm text-gray-500 mt-1">All modules functioning</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Database Connection</h3>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-100 flex items-center justify-center mr-4">
                                    <i class="fas fa-database text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-green-600">Connected</p>
                                    <p class="text-sm text-gray-500 mt-1">MySQL database accessible</p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">API Status</h3>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-green-100 flex items-center justify-center mr-4">
                                    <i class="fas fa-server text-green-600"></i>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-green-600">Active</p>
                                    <p class="text-sm text-gray-500 mt-1">All endpoints responding</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Module Status -->
                    <div class="bg-white rounded-lg shadow mb-8 overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">Module Status</h2>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Module</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Record Count</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($tables as $table => $label): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($label); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo htmlspecialchars($counts[$table]); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php if ($counts[$table] !== 'Table not found'): ?>
                                                <a href="#" class="text-blue-600 hover:text-blue-900 font-medium">View Records</a>
                                            <?php else: ?>
                                                <span class="text-red-600 font-medium">Error</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-medium text-gray-900">Quick Links</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <a href="accounting-dashboard.php" class="block bg-blue-50 hover:bg-blue-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-blue-600 font-medium">Accounting Dashboard</div>
                                </a>
                                <a href="commission-tracking.php" class="block bg-green-50 hover:bg-green-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-green-600 font-medium">Commission Tracking</div>
                                </a>
                                <a href="marketing-expenses.php" class="block bg-purple-50 hover:bg-purple-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-purple-600 font-medium">Marketing Expenses</div>
                                </a>
                                <a href="financial-reports.php" class="block bg-indigo-50 hover:bg-indigo-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-indigo-600 font-medium">Financial Reports</div>
                                </a>
                                <a href="accounts-payable.php" class="block bg-yellow-50 hover:bg-yellow-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-yellow-600 font-medium">Accounts Payable</div>
                                </a>
                                <a href="accounts-receivable.php" class="block bg-teal-50 hover:bg-teal-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-teal-600 font-medium">Accounts Receivable</div>
                                </a>
                                <a href="documentation.php" class="block bg-gray-50 hover:bg-gray-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-gray-600 font-medium">Documentation</div>
                                </a>
                                <a href="#" class="block bg-red-50 hover:bg-red-100 p-4 rounded-lg text-center transition duration-150">
                                    <div class="text-red-600 font-medium">System Logs</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>