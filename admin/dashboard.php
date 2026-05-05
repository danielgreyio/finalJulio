<?php
session_start();
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get admin statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'customer') as total_customers,
        (SELECT COUNT(*) FROM users WHERE role = 'merchant') as total_merchants,
        (SELECT COUNT(*) FROM products) as total_products,
        (SELECT COUNT(*) FROM orders) as total_orders,
        (SELECT COALESCE(SUM(total), 0) FROM orders) as total_revenue,
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') as pending_orders,
        (SELECT COUNT(*) FROM users WHERE role = 'merchant' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_merchants
";
$stmt = $pdo->prepare($statsQuery);
$stmt->execute();
$stats = $stmt->fetch();

// Get recent activities with more details
$recentActivitiesQuery = "
    (SELECT 'order' as type, o.id, o.user_id, o.total as amount, o.created_at, o.status,
            u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
            CONCAT('New order #', o.id, ' - $', FORMAT(o.total, 2)) as description
     FROM orders o
     LEFT JOIN users u ON o.user_id = u.id
     LEFT JOIN user_profiles up ON u.id = up.user_id
     ORDER BY o.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'customer' as type, u.id, u.id as user_id, 0 as amount, u.created_at, u.role as status,
            u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
            CONCAT('New customer registration: ', u.email) as description
     FROM users u
     LEFT JOIN user_profiles up ON u.id = up.user_id
     WHERE u.role = 'customer' AND u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY u.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'merchant' as type, ma.id, ma.user_id, 0 as amount, ma.created_at, ma.status,
            u.email as user_email, '' as first_name, '' as last_name,
            CONCAT('Merchant application: ', ma.business_name) as description
     FROM merchant_applications ma
     JOIN users u ON ma.user_id = u.id
     WHERE ma.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY ma.created_at DESC LIMIT 5)
    UNION ALL
    (SELECT 'product' as type, p.id, p.merchant_id as user_id, p.price as amount, p.created_at, 'added' as status,
            u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
            CONCAT('New product: ', p.name) as description
     FROM products p
     LEFT JOIN users u ON p.merchant_id = u.id
     LEFT JOIN user_profiles up ON u.id = up.user_id
     WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY p.created_at DESC LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 15
";
$stmt = $pdo->prepare($recentActivitiesQuery);
$stmt->execute();
$recentActivities = $stmt->fetchAll();

// Get daily revenue for chart (last 30 days)
$revenueQuery = "
    SELECT
        DATE(created_at) as order_date,
        COUNT(*) as order_count,
        COALESCE(SUM(total), 0) as daily_revenue
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY order_date ASC
";
$stmt = $pdo->prepare($revenueQuery);
$stmt->execute();
$rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process data to ensure all last 30 days are represented
$revenueData = [];
$map = [];

// Index existing data by date
foreach ($rawData as $day) {
    $map[$day['order_date']] = $day;
}

// Fill in last 30 days
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    if (isset($map[$date])) {
        $revenueData[] = $map[$date];
    } else {
        $revenueData[] = [
            'order_date' => $date,
            'order_count' => 0,
            'daily_revenue' => 0
        ];
    }
}

// Get top merchants by revenue with enhanced details
$topMerchantsQuery = "
    SELECT
        u.email,
        u.id,
        COALESCE(up.first_name, '') as first_name,
        COALESCE(up.last_name, '') as last_name,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT oi.order_id) as order_count,
        COALESCE(SUM(oi.price_at_purchase * oi.quantity), 0) as merchant_revenue,
        COUNT(DISTINCT CASE WHEN p.inventory > 0 THEN p.id END) as active_products,
        MAX(o.created_at) as last_sale_date,
        u.created_at as joined_date
    FROM users u
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN products p ON u.id = p.merchant_id
    LEFT JOIN order_items oi ON p.id = oi.product_id
    LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE u.role = 'merchant'
    GROUP BY u.id
    ORDER BY merchant_revenue DESC
    LIMIT 5
";
$stmt = $pdo->prepare($topMerchantsQuery);
$stmt->execute();
$topMerchants = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8 mb-6 flex justify-between items-center">
                    <h1 class="text-2xl font-semibold text-gray-900">Dashboard Overview</h1>
                    
                    <!-- Profile Dropdown -->
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 focus:outline-none">
                            <i class="fas fa-user-circle text-2xl"></i>
                            <span class="hidden md:inline"><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" x-cloak
                             class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50">
                            <a href="../index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Store</a>
                            <a href="settings.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Settings</a>
                            <div class="border-t border-gray-100"></div>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>

                <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                    <!-- Stats Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                        <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-md bg-blue-500 p-3">
                                            <i class="fas fa-users text-white"></i>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Total Customers</dt>
                                            <dd class="text-lg font-bold text-gray-900"><?= number_format($stats['total_customers']) ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-md bg-green-500 p-3">
                                            <i class="fas fa-store text-white"></i>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Merchants</dt>
                                            <dd class="flex items-baseline">
                                                <div class="text-lg font-bold text-gray-900"><?= number_format($stats['total_merchants']) ?></div>
                                                <?php if ($stats['new_merchants'] > 0): ?>
                                                    <div class="ml-2 flex items-baseline text-sm font-semibold text-green-600">
                                                        <i class="fas fa-arrow-up self-center flex-shrink-0 h-3 w-3 text-green-500"></i>
                                                        <span class="sr-only">Increased by</span>
                                                        <?= $stats['new_merchants'] ?>
                                                    </div>
                                                <?php endif; ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-md bg-yellow-500 p-3">
                                            <i class="fas fa-shopping-cart text-white"></i>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Total Orders</dt>
                                            <dd class="flex items-baseline">
                                                <div class="text-lg font-bold text-gray-900"><?= number_format($stats['total_orders']) ?></div>
                                                <?php if ($stats['pending_orders'] > 0): ?>
                                                    <div class="ml-2 text-xs font-medium text-yellow-600 bg-yellow-100 px-2 py-0.5 rounded-full">
                                                        <?= $stats['pending_orders'] ?> pending
                                                    </div>
                                                <?php endif; ?>
                                            </dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-md transition-shadow duration-200">
                            <div class="p-5">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <div class="rounded-md bg-purple-500 p-3">
                                            <i class="fas fa-dollar-sign text-white"></i>
                                        </div>
                                    </div>
                                    <div class="ml-5 w-0 flex-1">
                                        <dl>
                                            <dt class="text-sm font-medium text-gray-500 truncate">Revenue</dt>
                                            <dd class="text-lg font-bold text-gray-900">$<?= number_format($stats['total_revenue'], 2) ?></dd>
                                        </dl>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Grid: Charts & Action Items -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
                        <!-- Revenue Chart (Larger) -->
                        <div class="lg:col-span-2 bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Revenue Trend (30 Days)</h2>
                            <div class="relative h-72">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>

                        <!-- Action Items (Compact Quick Actions) -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-lg font-medium text-gray-900 mb-4">Pending Tasks</h2>
                            <div class="space-y-4">
                                <?php if ($stats['new_merchants'] > 0 || $stats['pending_orders'] > 0): ?>
                                    
                                    <?php if ($stats['pending_orders'] > 0): ?>
                                    <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg border border-yellow-100 hover:bg-yellow-100 transition-colors cursor-pointer" onclick="location.href='orders.php?status=pending'">
                                        <div class="flex items-center">
                                            <i class="fas fa-box text-yellow-600 mr-3"></i>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?= $stats['pending_orders'] ?> Orders Pending</p>
                                                <p class="text-xs text-gray-500">Require processing</p>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                                    </div>
                                    <?php endif; ?>

                                    <?php if ($stats['new_merchants'] > 0): ?>
                                    <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg border border-green-100 hover:bg-green-100 transition-colors cursor-pointer" onclick="location.href='merchants.php?sort=newest'">
                                        <div class="flex items-center">
                                            <i class="fas fa-store text-green-600 mr-3"></i>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900"><?= $stats['new_merchants'] ?> New Merchants</p>
                                                <p class="text-xs text-gray-500">Since last week</p>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-right text-gray-400 text-xs"></i>
                                    </div>
                                    <?php endif; ?>

                                <?php else: ?>
                                    <div class="text-center py-8 text-gray-500">
                                        <i class="fas fa-check-circle text-4xl text-green-400 mb-3"></i>
                                        <p>All caught up!</p>
                                        <p class="text-xs">No pending tasks requiring attention.</p>
                                    </div>
                                <?php endif; ?>

                                <div class="border-t border-gray-100 pt-4 mt-2">
                                    <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Shortcuts</h3>
                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="users.php" class="text-xs text-center p-2 bg-gray-50 rounded hover:bg-gray-100 text-gray-600">
                                            <i class="fas fa-user-plus block mb-1"></i> Add User
                                        </a>
                                        <a href="products.php" class="text-xs text-center p-2 bg-gray-50 rounded hover:bg-gray-100 text-gray-600">
                                            <i class="fas fa-plus block mb-1"></i> Add Product
                                        </a>
                                        <a href="reports.php" class="text-xs text-center p-2 bg-gray-50 rounded hover:bg-gray-100 text-gray-600">
                                            <i class="fas fa-file-alt block mb-1"></i> Reports
                                        </a>
                                        <a href="settings.php" class="text-xs text-center p-2 bg-gray-50 rounded hover:bg-gray-100 text-gray-600">
                                            <i class="fas fa-cog block mb-1"></i> Settings
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Grid: Recent Activities & Top Merchants -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                        <!-- Recent Activities -->
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-lg font-medium text-gray-900">Recent Activity</h2>
                                <a href="activity-log.php" class="text-sm text-blue-600 hover:text-blue-900">View all</a>
                            </div>
                            <div class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                                <?php if (empty($recentActivities)): ?>
                                    <p class="p-6 text-gray-500 text-center">No recent activities found.</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($recentActivities, 0, 8) as $activity): ?>
                                        <div class="p-4 hover:bg-gray-50 transition-colors">
                                            <div class="flex items-center space-x-3">
                                                <div class="flex-shrink-0">
                                                    <span class="inline-flex items-center justify-center h-8 w-8 rounded-full <?php
                                                        switch($activity['type']) {
                                                            case 'order': echo 'bg-blue-100'; break;
                                                            case 'customer': echo 'bg-green-100'; break;
                                                            case 'merchant': echo 'bg-orange-100'; break;
                                                            case 'product': echo 'bg-purple-100'; break;
                                                            default: echo 'bg-gray-100';
                                                        }
                                                    ?>">
                                                        <i class="fas <?php
                                                            switch($activity['type']) {
                                                                case 'order': echo 'fa-shopping-cart text-blue-600'; break;
                                                                case 'customer': echo 'fa-user text-green-600'; break;
                                                                case 'merchant': echo 'fa-store text-orange-600'; break;
                                                                case 'product': echo 'fa-box text-purple-600'; break;
                                                                default: echo 'fa-bell text-gray-600';
                                                            }
                                                        ?> text-xs"></i>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <p class="text-sm font-medium text-gray-900 truncate">
                                                        <?= htmlspecialchars($activity['description']) ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        <?= date('M j, g:i a', strtotime($activity['created_at'])) ?> · <?= htmlspecialchars($activity['user_email']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Top Merchants -->
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h2 class="text-lg font-medium text-gray-900">Top Merchants</h2>
                                <a href="merchants.php" class="text-sm text-blue-600 hover:text-blue-900">View all</a>
                            </div>
                            <div class="divide-y divide-gray-200">
                                <?php if (empty($topMerchants)): ?>
                                    <p class="p-6 text-gray-500 text-center">No merchant data available.</p>
                                <?php else: ?>
                                    <?php foreach ($topMerchants as $index => $merchant): ?>
                                        <div class="p-4 hover:bg-gray-50 transition-colors flex items-center justify-between">
                                            <div class="flex items-center">
                                                <span class="text-gray-400 font-bold mr-3 w-4"><?= $index + 1 ?></span>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        <?php
                                                        $displayName = trim($merchant['first_name'] . ' ' . $merchant['last_name']);
                                                        echo htmlspecialchars($displayName ?: $merchant['email']);
                                                        ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500"><?= number_format($merchant['order_count']) ?> orders · <?= number_format($merchant['active_products']) ?> products</p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-sm font-bold text-gray-900">$<?= number_format($merchant['merchant_revenue'], 2) ?></p>
                                                <a href="merchant-details.php?id=<?= $merchant['id'] ?>" class="text-xs text-blue-600 hover:underline">View</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const canvas = document.getElementById('revenueChart');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                const revenueData = <?= json_encode($revenueData) ?>;

                if (revenueData && revenueData.length > 0) {
                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: revenueData.map(item => {
                                const date = new Date(item.order_date);
                                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                            }),
                            datasets: [{
                                label: 'Revenue',
                                data: revenueData.map(item => parseFloat(item.daily_revenue) || 0),
                                borderColor: 'rgb(59, 130, 246)',
                                backgroundColor: 'rgba(59, 130, 246, 0.05)',
                                borderWidth: 2,
                                tension: 0.3,
                                fill: true,
                                pointRadius: 0,
                                pointHoverRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    grid: { borderDash: [2, 4], color: '#f3f4f6' },
                                    ticks: { callback: value => '$' + value }
                                },
                                x: {
                                    grid: { display: false }
                                }
                            }
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
