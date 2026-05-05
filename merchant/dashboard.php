<?php
require_once '../config/database.php';

// Require merchant login
requireRole('merchant');

$merchantId = $_SESSION['user_id'];

// 1. Get Merchant Statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_products,
        COALESCE(SUM(inventory), 0) as total_inventory,
        COUNT(CASE WHEN inventory > 0 THEN 1 END) as active_products,
        COUNT(CASE WHEN inventory <= 0 THEN 1 END) as out_of_stock
    FROM products 
    WHERE merchant_id = ?
");
$stmt->execute([$merchantId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Get Recent Orders
$stmt = $pdo->prepare("
    SELECT o.*, u.email as customer_email 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    WHERE o.merchant_id = ? 
    ORDER BY o.created_at DESC 
    LIMIT 5
");
$stmt->execute([$merchantId]);
$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Get Sales Data (Last 7 Days)
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as order_date, SUM(total) as daily_revenue 
    FROM orders 
    WHERE merchant_id = ? 
      AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    GROUP BY DATE(created_at) 
    ORDER BY order_date ASC
");
$stmt->execute([$merchantId]);
$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If no sales data, initialize empty array to avoid chart errors
if (empty($salesData)) {
    $salesData = [];
    // Optional: Fill with 0 for last 7 days used by Chart.js if needed
    // For now we pass empty, Chart.js handles it or logic below handles it
}

// 4. Get Top Selling Products (Based on Order Items)
$stmt = $pdo->prepare("
    SELECT p.name, p.price, p.inventory as stock, SUM(oi.quantity) as order_count 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE p.merchant_id = ? 
    GROUP BY p.id 
    ORDER BY order_count DESC 
    LIMIT 5
");
$stmt->execute([$merchantId]);
$topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Merchant Dashboard - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Merchant Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <span class="text-lg font-semibold text-gray-700">Merchant Dashboard</span>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="add-product.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Product
                    </a>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        <div x-show="open" @click.away="open = false" 
                             class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50">
                            <a href="../index.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Store</a>
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="../logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Welcome Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Welcome back!</h1>
            <p class="text-gray-600 mt-2">Here's what's happening with your store today.</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-box text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_products'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-warehouse text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Inventory</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['total_inventory'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-check-circle text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Active Products</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['active_products'] ?></p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Out of Stock</p>
                        <p class="text-2xl font-bold text-gray-900"><?= $stats['out_of_stock'] ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Sales Chart -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Sales Overview (Last 7 Days)</h2>
                <canvas id="salesChart" width="400" height="200"></canvas>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Quick Actions</h2>
                <div class="space-y-4">
                    <a href="add-product.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-blue-100 rounded-lg">
                            <i class="fas fa-plus text-blue-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Add New Product</h3>
                            <p class="text-sm text-gray-600">List a new product in your store</p>
                        </div>
                    </a>

                    <a href="products.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-green-100 rounded-lg">
                            <i class="fas fa-edit text-green-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Manage Products</h3>
                            <p class="text-sm text-gray-600">Edit existing products and inventory</p>
                        </div>
                    </a>

                    <a href="orders.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-yellow-100 rounded-lg">
                            <i class="fas fa-shopping-bag text-yellow-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">View Orders</h3>
                            <p class="text-sm text-gray-600">Check recent orders and fulfillment</p>
                        </div>
                    </a>

                    <a href="analytics.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <div class="p-2 bg-purple-100 rounded-lg">
                            <i class="fas fa-chart-bar text-purple-600"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Analytics</h3>
                            <p class="text-sm text-gray-600">View detailed sales analytics</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Orders and Top Products -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
            <!-- Recent Orders -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Recent Orders</h2>
                <?php if (empty($recentOrders)): ?>
                    <p class="text-gray-500 text-center py-8">No recent orders</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900">Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></p>
                                    <p class="text-sm text-gray-600"><?= htmlspecialchars($order['customer_email']) ?></p>
                                    <p class="text-xs text-gray-500"><?= date('M j, Y', strtotime($order['created_at'])) ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-gray-900">$<?= number_format($order['total'], 2) ?></p>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        <?php
                                        switch($order['status']) {
                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                            case 'shipped': echo 'bg-blue-100 text-blue-800'; break;
                                            case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                            default: echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="orders.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">View All Orders →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Products -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Products (Last 30 Days)</h2>
                <?php if (empty($topProducts)): ?>
                    <p class="text-gray-500 text-center py-8">No product data available</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($topProducts as $product): ?>
                            <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
                                <div>
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></p>
                                    <p class="text-sm text-gray-600">$<?= number_format($product['price'], 2) ?></p>
                                    <p class="text-xs text-gray-500">Stock: <?= $product['stock'] ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-semibold text-blue-600"><?= $product['order_count'] ?></p>
                                    <p class="text-xs text-gray-500">orders</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-center">
                        <a href="products.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Manage Products →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart').getContext('2d');
        const salesData = <?= json_encode(array_reverse($salesData)) ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: salesData.map(item => new Date(item.order_date).toLocaleDateString()),
                datasets: [{
                    label: 'Daily Revenue',
                    data: salesData.map(item => parseFloat(item.daily_revenue)),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
