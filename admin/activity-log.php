<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get filter parameters
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

// Build comprehensive activities query
$whereConditions = [];
$params = [];

if ($type) {
    $whereConditions[] = "activity_type = ?";
    $params[] = $type;
}

if ($date_from) {
    $whereConditions[] = "DATE(activity_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $whereConditions[] = "DATE(activity_date) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $whereConditions[] = "(description LIKE ? OR user_email LIKE ? OR details LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get comprehensive activities
$activitiesQuery = "
    SELECT * FROM (
        (SELECT 'order' as activity_type, o.id as entity_id, o.user_id, o.total as amount,
                o.created_at as activity_date, o.status, u.email as user_email,
                COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
                CONCAT('Order #', LPAD(o.id, 6, '0'), ' - ', UPPER(o.status)) as description,
                JSON_OBJECT('order_id', o.id, 'total', o.total, 'items',
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id),
                    'shipping_address', o.shipping_address) as details
         FROM orders o
         LEFT JOIN users u ON o.user_id = u.id
         LEFT JOIN user_profiles up ON u.id = up.user_id
         WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
        
        UNION ALL
        
        (SELECT 'customer' as activity_type, u.id as entity_id, u.id as user_id, 0 as amount,
                u.created_at as activity_date, u.role as status, u.email as user_email,
                COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
                CONCAT('New customer registration: ', COALESCE(CONCAT(up.first_name, ' ', up.last_name), u.email)) as description,
                JSON_OBJECT('user_id', u.id, 'email', u.email, 'verified', 0, 'phone', up.phone) as details
         FROM users u
         LEFT JOIN user_profiles up ON u.id = up.user_id
         WHERE u.role = 'customer' AND u.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
        
        UNION ALL
        
        (SELECT 'merchant' as activity_type, ma.id as entity_id, ma.user_id, ma.estimated_monthly_sales as amount,
                ma.created_at as activity_date, ma.status, ma.contact_email as user_email, ma.contact_name as first_name, '' as last_name,
                CONCAT('Merchant application: ', ma.business_name, ' (', UPPER(ma.status), ')') as description,
                JSON_OBJECT('business_name', ma.business_name, 'business_type', ma.business_type, 
                    'estimated_sales', ma.estimated_monthly_sales, 'contact_email', ma.contact_email) as details
         FROM merchant_applications ma 
         WHERE ma.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
        
        UNION ALL
        
        (SELECT 'product' as activity_type, p.id as entity_id, p.merchant_id as user_id, p.price as amount,
                p.created_at as activity_date, CASE WHEN p.stock > 0 THEN 'in_stock' ELSE 'out_of_stock' END as status,
                u.email as user_email, COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
                CONCAT('Product added: ', p.name, ' by ', u.email) as description,
                JSON_OBJECT('product_id', p.id, 'name', p.name, 'price', p.price, 'category', p.category, 'stock', COALESCE(p.stock_quantity, p.stock)) as details
         FROM products p
         LEFT JOIN users u ON p.merchant_id = u.id
         LEFT JOIN user_profiles up ON u.id = up.user_id
         WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
        
        UNION ALL
        
        (SELECT 'shipment' as activity_type, s.id as entity_id, o.user_id, s.shipping_cost as amount,
                s.created_at as activity_date, s.status, u.email as user_email,
                COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
                CONCAT('Shipment ', s.tracking_number, ' - ', UPPER(s.status)) as description,
                JSON_OBJECT('tracking_number', s.tracking_number, 'carrier', COALESCE(sp.name, 'Unknown'), 'cost', s.shipping_cost) as details
         FROM shipments s
         LEFT JOIN orders o ON s.order_id = o.id
         LEFT JOIN users u ON o.user_id = u.id
         LEFT JOIN user_profiles up ON u.id = up.user_id
         LEFT JOIN shipping_providers sp ON s.provider_id = sp.id
         WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))
    ) as activities
    $whereClause
    ORDER BY activity_date DESC
    LIMIT 100
";

$stmt = $pdo->prepare($activitiesQuery);
$stmt->execute($params);
$activities = $stmt->fetchAll();

// Get activity statistics
$stats = [
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'new_customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'merchant_applications' => $pdo->query("SELECT COUNT(*) FROM merchant_applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn(),
    'new_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - VentDepot Admin</title>
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

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Activity Log</h1>
                <p class="text-gray-600 mt-2">Comprehensive view of all system activities</p>
            </div>
            <a href="dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
        </div>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-shopping-cart text-blue-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Orders (30 days)</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_orders']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-user text-green-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">New Customers</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['new_customers']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-store text-orange-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Merchant Apps</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['merchant_applications']) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <i class="fas fa-box text-purple-600 text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">New Products</p>
                        <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['new_products']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                    <select name="type" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="">All Types</option>
                        <option value="order" <?= $type === 'order' ? 'selected' : '' ?>>Orders</option>
                        <option value="customer" <?= $type === 'customer' ? 'selected' : '' ?>>Customers</option>
                        <option value="merchant" <?= $type === 'merchant' ? 'selected' : '' ?>>Merchants</option>
                        <option value="product" <?= $type === 'product' ? 'selected' : '' ?>>Products</option>
                        <option value="shipment" <?= $type === 'shipment' ? 'selected' : '' ?>>Shipments</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Description, email, etc..."
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-end">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Activities Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Recent Activities (<?= count($activities) ?> shown)</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($activities)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                    No activities found for the selected criteria.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($activities as $activity): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="p-2 rounded-lg <?php
                                                switch($activity['activity_type']) {
                                                    case 'order': echo 'bg-blue-100'; break;
                                                    case 'customer': echo 'bg-green-100'; break;
                                                    case 'merchant': echo 'bg-orange-100'; break;
                                                    case 'product': echo 'bg-purple-100'; break;
                                                    case 'shipment': echo 'bg-yellow-100'; break;
                                                    default: echo 'bg-gray-100';
                                                }
                                            ?>">
                                                <i class="fas <?php
                                                    switch($activity['activity_type']) {
                                                        case 'order': echo 'fa-shopping-cart text-blue-600'; break;
                                                        case 'customer': echo 'fa-user text-green-600'; break;
                                                        case 'merchant': echo 'fa-store text-orange-600'; break;
                                                        case 'product': echo 'fa-box text-purple-600'; break;
                                                        case 'shipment': echo 'fa-shipping-fast text-yellow-600'; break;
                                                        default: echo 'fa-circle text-gray-600';
                                                    }
                                                ?>"></i>
                                            </div>
                                            <span class="ml-2 text-sm font-medium text-gray-900 capitalize"><?= $activity['activity_type'] ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($activity['description']) ?></div>
                                        <div class="text-sm text-gray-500">ID: <?= $activity['entity_id'] ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($activity['user_email']) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php if ($activity['amount'] > 0): ?>
                                            $<?= number_format($activity['amount'], 2) ?>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?= date('M j, Y', strtotime($activity['activity_date'])) ?>
                                        <div class="text-xs text-gray-500"><?= date('H:i', strtotime($activity['activity_date'])) ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                            <?php
                                            switch(strtolower($activity['status'])) {
                                                case 'completed': case 'delivered': case 'active': case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                case 'pending': case 'processing': echo 'bg-yellow-100 text-yellow-800'; break;
                                                case 'cancelled': case 'rejected': case 'inactive': echo 'bg-red-100 text-red-800'; break;
                                                case 'shipped': case 'in_transit': echo 'bg-blue-100 text-blue-800'; break;
                                                default: echo 'bg-gray-100 text-gray-800';
                                            }
                                            ?>">
                                            <?= ucfirst($activity['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button onclick="viewDetails('<?= $activity['activity_type'] ?>', <?= $activity['entity_id'] ?>)" 
                                                class="text-blue-600 hover:text-blue-900">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function viewDetails(type, id) {
            let url = '';
            switch(type) {
                case 'order':
                    url = `orders.php?search=${id}`;
                    break;
                case 'customer':
                    url = `users.php?search=${id}`;
                    break;
                case 'merchant':
                    url = `merchants.php?search=${id}`;
                    break;
                case 'product':
                    url = `products.php?search=${id}`;
                    break;
                case 'shipment':
                    url = `global-shipping-admin.php?search=${id}`;
                    break;
                default:
                    return;
            }
            window.location.href = url;
        }
    </script>
</body>
</html>
