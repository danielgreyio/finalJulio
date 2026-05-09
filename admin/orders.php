<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Handle order actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if ($action === 'update_status' && $orderId > 0) {
        $newStatus = $_POST['status'] ?? '';
        if (in_array($newStatus, ['pending', 'shipped', 'delivered', 'cancelled'])) {
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            $success = 'Order status updated successfully.';
        }
    } elseif ($action === 'process_refund' && $orderId > 0) {
        $refundAmount = floatval($_POST['refund_amount'] ?? 0);
        $refundReason = $_POST['refund_reason'] ?? '';
        
        // In real app, process actual refund through payment gateway
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
        $stmt->execute([$orderId]);
        $success = "Refund of $" . number_format($refundAmount, 2) . " processed successfully. (Demo: Payment gateway would be called)";
    }
}

// Get orders with pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$allowedSortColumns = ['id', 'created_at', 'total', 'status', 'customer_id'];
$sortBy = in_array($_GET['sort'] ?? '', $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = strtoupper($_GET['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = '(o.id LIKE ? OR u.email LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $whereConditions[] = 'o.status = ?';
    $params[] = $status;
}

if (!empty($dateFrom)) {
    $whereConditions[] = 'DATE(o.created_at) >= ?';
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = 'DATE(o.created_at) <= ?';
    $params[] = $dateTo;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countSql = "
    SELECT COUNT(*) 
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalOrders = $countStmt->fetchColumn();
$totalPages = ceil($totalOrders / $perPage);

// Get orders
$sql = "
    SELECT o.*, u.email as customer_email, u.id as customer_id
    FROM orders o 
    JOIN users u ON o.user_id = u.id 
    $whereClause
    ORDER BY o.$sortBy $sortOrder
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get order statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
        COUNT(CASE WHEN status = 'shipped' THEN 1 END) as shipped_orders,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_orders,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
        COALESCE(SUM(total), 0) as total_revenue,
        COALESCE(AVG(total), 0) as avg_order_value
    FROM orders
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute();
$orderStats = $statsStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Orders - VentDepot Admin</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">Monitor Orders</h1>
                            <p class="text-gray-600 mt-2">Track and manage all platform orders</p>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            Total: <?= number_format($totalOrders) ?> orders
                        </div>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <!-- Order Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100">
                                    <i class="fas fa-shopping-cart text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Orders (30d)</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($orderStats['total_orders']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100">
                                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Pending Orders</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($orderStats['pending_orders']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100">
                                    <i class="fas fa-dollar-sign text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Revenue (30d)</p>
                                    <p class="text-2xl font-bold text-gray-900">$<?= number_format($orderStats['total_revenue'], 2) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100">
                                    <i class="fas fa-chart-line text-purple-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Avg Order Value</p>
                                    <p class="text-2xl font-bold text-gray-900">$<?= number_format($orderStats['avg_order_value'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="Order ID or customer email...">
                            </div>
                            
                            <div>
                                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" id="status"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Orders</option>
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="shipped" <?= $status === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                    <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                                <input type="date" name="date_from" id="date_from" value="<?= htmlspecialchars($dateFrom) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                                <input type="date" name="date_to" id="date_to" value="<?= htmlspecialchars($dateTo) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-search mr-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Orders List -->
                    <?php if (empty($orders)): ?>
                        <div class="bg-white rounded-lg shadow-md p-12 text-center">
                            <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">No orders found</h3>
                            <p class="text-gray-500">Try adjusting your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($orders as $order): ?>
                                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                    <!-- Order Header -->
                                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900">
                                                    Order #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?>
                                                </h3>
                                                <p class="text-sm text-gray-600">
                                                    Customer: <?= htmlspecialchars($order['customer_email']) ?>
                                                </p>
                                                <p class="text-sm text-gray-600">
                                                    Placed: <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
                                                </p>
                                            </div>
                                            <div class="mt-2 sm:mt-0 flex items-center space-x-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                    <?php
                                                    switch($order['status']) {
                                                        case 'pending':
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'shipped':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'delivered':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'cancelled':
                                                            echo 'bg-red-100 text-red-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                    ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                                <span class="text-lg font-semibold text-gray-900">
                                                    $<?= number_format($order['total'], 2) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Order Details -->
                                    <div class="px-6 py-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <h4 class="font-medium text-gray-900 mb-2">Shipping Address</h4>
                                                <p class="text-sm text-gray-600">
                                                    <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                                                </p>
                                            </div>
                                            <div>
                                                <h4 class="font-medium text-gray-900 mb-2">Payment & Shipping</h4>
                                                <div class="text-sm text-gray-600 space-y-1">
                                                    <p><strong>Payment:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                                                    <p><strong>Shipping Cost:</strong> $<?= number_format($order['shipping_cost'] ?? 0.00, 2) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Order Actions -->
                                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                            <div class="flex space-x-4 mb-2 sm:mb-0">
                                                <a href="order-details.php?id=<?= $order['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                    <i class="fas fa-eye mr-1"></i>View Details
                                                </a>
                                                
                                                <button onclick="processRefund(<?= $order['id'] ?>, <?= $order['total'] ?>)"
                                                        class="text-red-600 hover:text-red-800 text-sm font-medium">
                                                    <i class="fas fa-undo mr-1"></i>Process Refund
                                                </button>
                                            </div>
                                            
                                            <!-- Status Update -->
                                            <div class="flex items-center space-x-2">
                                                <form method="POST" class="flex items-center space-x-2">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                    <select name="status" 
                                                            class="text-sm border border-gray-300 rounded px-2 py-1 focus:ring-2 focus:ring-blue-500">
                                                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                    <button type="submit" 
                                                            class="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                                                        Update
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex justify-center mt-8">
                                <nav class="flex space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                           class="px-3 py-2 border rounded-md <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Refund Modal -->
    <div id="refundModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Process Refund</h3>
                <form method="POST" id="refundForm">
                    <input type="hidden" name="action" value="process_refund">
                    <input type="hidden" name="order_id" id="refundOrderId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Refund Amount
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="refund_amount" id="refundAmount" step="0.01" required
                                   class="w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Refund Reason
                        </label>
                        <textarea name="refund_reason" rows="3" required
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                  placeholder="Please provide a reason for the refund..."></textarea>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeRefundModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                            Process Refund
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function processRefund(orderId, orderTotal) {
            document.getElementById('refundOrderId').value = orderId;
            document.getElementById('refundAmount').value = orderTotal.toFixed(2);
            document.getElementById('refundModal').classList.remove('hidden');
        }
        
        function closeRefundModal() {
            document.getElementById('refundModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('refundModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRefundModal();
            }
        });
    </script>
</body>
</html>
