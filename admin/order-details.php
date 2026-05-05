<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$orderId = intval($_GET['id'] ?? 0);

if (!$orderId) {
    header('Location: orders.php');
    exit;
}

// Get order details
$orderQuery = "
    SELECT o.*, u.email as customer_email,
           COALESCE(up.first_name, '') as first_name, COALESCE(up.last_name, '') as last_name,
           up.phone as customer_phone,
           s.tracking_number, sp.name as carrier, s.status as shipment_status, s.shipping_cost
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    LEFT JOIN user_profiles up ON u.id = up.user_id
    LEFT JOIN shipments s ON o.id = s.order_id
    LEFT JOIN shipping_providers sp ON s.provider_id = sp.id
    WHERE o.id = ?
";
$stmt = $pdo->prepare($orderQuery);
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get order items
$itemsQuery = "
    SELECT oi.*, p.name as product_name, p.image_url, u.email as merchant_email
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    LEFT JOIN users u ON p.merchant_id = u.id
    WHERE oi.order_id = ?
";
$stmt = $pdo->prepare($itemsQuery);
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?> - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?></h1>
                <p class="text-gray-600 mt-2">Order placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
            </div>
            <button onclick="closeWindow()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700 no-print transition-colors duration-200">
                <i class="fas fa-times mr-2"></i>Close
            </button>
        </div>

        <!-- Order Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Order Status</h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium mt-2
                        <?php
                        switch(strtolower($order['status'])) {
                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'processing': echo 'bg-blue-100 text-blue-800'; break;
                            case 'shipped': echo 'bg-purple-100 text-purple-800'; break;
                            case 'cancelled': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?= ucfirst($order['status']) ?>
                    </span>
                </div>
                <div class="text-right">
                    <div class="text-2xl font-bold text-gray-900">$<?= number_format($order['total'], 2) ?></div>
                    <div class="text-sm text-gray-500">Total Amount</div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Customer Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Customer Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Name</label>
                        <p class="text-gray-900"><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Email</label>
                        <p class="text-gray-900"><?= htmlspecialchars($order['customer_email']) ?></p>
                    </div>
                    <?php if ($order['customer_phone']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Phone</label>
                        <p class="text-gray-900"><?= htmlspecialchars($order['customer_phone']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipping Information</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Address</label>
                        <p class="text-gray-900 whitespace-pre-line"><?= htmlspecialchars($order['shipping_address']) ?></p>
                    </div>
                    <?php if ($order['tracking_number']): ?>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Tracking Number</label>
                        <p class="text-gray-900 font-mono"><?= htmlspecialchars($order['tracking_number']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Carrier</label>
                        <p class="text-gray-900"><?= htmlspecialchars($order['carrier']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Shipping Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?php
                            switch(strtolower($order['shipment_status'])) {
                                case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                case 'in_transit': echo 'bg-blue-100 text-blue-800'; break;
                                case 'picked_up': echo 'bg-yellow-100 text-yellow-800'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                            ?>">
                            <?= ucfirst($order['shipment_status']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Order Items -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Items</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Merchant</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantity</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <?php if ($item['image_url']): ?>
                                            <img class="h-10 w-10 rounded-lg object-cover mr-4" 
                                                 src="<?= htmlspecialchars($item['image_url']) ?>" 
                                                 alt="<?= htmlspecialchars($item['product_name']) ?>">
                                        <?php else: ?>
                                            <div class="h-10 w-10 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                                <i class="fas fa-image text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                            <div class="text-sm text-gray-500">ID: <?= $item['product_id'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($item['merchant_email']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    $<?= number_format($item['price_at_purchase'], 2) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $item['quantity'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    $<?= number_format($item['price_at_purchase'] * $item['quantity'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Summary</h2>
            <div class="space-y-2">
                <div class="flex justify-between">
                    <span class="text-gray-600">Subtotal:</span>
                    <span class="text-gray-900">$<?= number_format(array_sum(array_map(function($item) { return $item['price_at_purchase'] * $item['quantity']; }, $items)), 2) ?></span>
                </div>
                <?php if ($order['shipping_cost']): ?>
                <div class="flex justify-between">
                    <span class="text-gray-600">Shipping:</span>
                    <span class="text-gray-900">$<?= number_format($order['shipping_cost'], 2) ?></span>
                </div>
                <?php endif; ?>
                <div class="border-t border-gray-200 pt-2">
                    <div class="flex justify-between">
                        <span class="text-lg font-semibold text-gray-900">Total:</span>
                        <span class="text-lg font-semibold text-gray-900">$<?= number_format($order['total'], 2) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Information -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Payment Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-gray-500">Payment Method</label>
                    <p class="text-gray-900"><?= htmlspecialchars($order['payment_method'] ?? 'Credit Card') ?></p>
                </div>
                <div>
                    <label class="text-sm font-medium text-gray-500">Payment Status</label>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        <?php
                        switch(strtolower($order['payment_status'] ?? 'completed')) {
                            case 'completed': echo 'bg-green-100 text-green-800'; break;
                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                            case 'failed': echo 'bg-red-100 text-red-800'; break;
                            default: echo 'bg-gray-100 text-gray-800';
                        }
                        ?>">
                        <?= ucfirst($order['payment_status'] ?? 'Completed') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="bg-white rounded-lg shadow-md p-6 mt-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Actions</h2>
            <div class="flex space-x-4 no-print">
                <a href="orders.php?search=<?= $orderId ?>" target="_blank" 
                   class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 transition-colors duration-200">
                    <i class="fas fa-external-link-alt mr-2"></i>View in Orders
                </a>
                <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 transition-colors duration-200">
                    <i class="fas fa-print mr-2"></i>Print Order
                </button>
                <?php if ($order['customer_email']): ?>
                <a href="mailto:<?= htmlspecialchars($order['customer_email']) ?>?subject=Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?>" 
                   class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700 transition-colors duration-200">
                    <i class="fas fa-envelope mr-2"></i>Email Customer
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .shadow-md { box-shadow: none !important; }
        }
    </style>

    <script>
        function closeWindow() {
            // Try to close the window (works for popups)
            if (window.opener || window.history.length === 1) {
                window.close();
                
                // If close didn't work after a brief delay, redirect
                setTimeout(function() {
                    if (!window.closed) {
                        window.location.href = 'orders.php';
                    }
                }, 100);
            } else {
                // Navigate back or to orders page
                if (document.referrer && document.referrer.indexOf(window.location.hostname) !== -1) {
                    window.history.back();
                } else {
                    window.location.href = 'orders.php';
                }
            }
        }
    </script>
</body>
</html>
