<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    header('Location: orders.php');
    exit;
}

// Get order details
// Ensuring we filter by user_id for security
$stmt = $pdo->prepare("
    SELECT o.*, pt.gateway_reference, pt.payment_method, 
           u.email as customer_email
    FROM orders o
    LEFT JOIN payment_transactions pt ON o.id = pt.order_id
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    // Order not found or doesn't belong to this user
    header('Location: orders.php');
    exit;
}

// Get order items
$stmt = $pdo->prepare("
    SELECT oi.*, oi.price_at_purchase as price, p.name as product_name, p.image_url, u.email as merchant_email
    FROM order_items oi
    JOIN products p ON oi.product_id = p.id
    JOIN users u ON p.merchant_id = u.id
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll();

$confirmationNumber = 'VD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($order['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?> - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50 text-gray-800">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="orders.php" class="text-blue-600 hover:text-blue-800 mb-2 inline-block">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Orders
                </a>
                <h1 class="text-3xl font-bold text-gray-900">Order Details</h1>
                <p class="text-gray-600">Order #<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?> • Placed on <?= date('M j, Y', strtotime($order['created_at'])) ?></p>
            </div>
            <div>
                <button onclick="window.print()" class="bg-white border border-gray-300 text-gray-700 hover:bg-gray-50 font-semibold py-2 px-4 rounded shadow-sm inline-flex items-center">
                    <i class="fas fa-print mr-2"></i> Print
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden border border-gray-200">
                    <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                        <h2 class="font-semibold text-gray-900">Items Ordered</h2>
                    </div>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="p-6 flex gap-4">
                                <div class="w-20 h-20 flex-shrink-0">
                                    <img src="<?= htmlspecialchars($item['image_url'] ?: 'assets/images/placeholder.jpg') ?>" 
                                         alt="<?= htmlspecialchars($item['product_name']) ?>"
                                         class="w-full h-full object-cover rounded-md border border-gray-200">
                                </div>
                                <div class="flex-grow">
                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></h3>
                                    <p class="text-sm text-gray-500 mb-2">Sold by: <?= htmlspecialchars($item['merchant_email']) ?></p>
                                    <div class="text-sm">
                                        <span class="text-gray-600">Qty: <?= $item['quantity'] ?></span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-gray-900">$<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                                    <p class="text-sm text-gray-500">$<?= number_format($item['price'], 2) ?> each</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Order Status Chain (Simplified visual) -->
                <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
                    <h2 class="font-semibold text-gray-900 mb-4">Order Status</h2>
                    <div class="relative">
                        <div class="absolute left-4 top-0 h-full w-0.5 bg-gray-200 h-full"></div>
                        <ul class="relative space-y-6 pl-10">
                            <!-- Placed -->
                            <li class="relative">
                                <span class="absolute -left-[30px] flex h-6 w-6 items-center justify-center rounded-full bg-green-500 ring-4 ring-white">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </span>
                                <div>
                                    <h3 class="font-medium text-gray-900">Order Placed</h3>
                                    <p class="text-sm text-gray-500"><?= date('M j, Y', strtotime($order['created_at'])) ?></p>
                                </div>
                            </li>
                            <!-- Processed/Pending -->
                             <li class="relative">
                                <span class="absolute -left-[30px] flex h-6 w-6 items-center justify-center rounded-full 
                                    <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? 'bg-green-500' : 'bg-gray-300' ?> ring-4 ring-white">
                                    <?php if(in_array($order['status'], ['processing', 'shipped', 'delivered'])): ?>
                                        <i class="fas fa-check text-white text-xs"></i>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <h3 class="font-medium text-gray-900 <?= in_array($order['status'], ['processing', 'shipped', 'delivered']) ? '' : 'text-gray-500' ?>">Processing</h3>
                                     <?php if($order['status'] === 'pending'): ?>
                                        <p class="text-sm text-blue-600">Current Status</p>
                                     <?php endif; ?>
                                </div>
                            </li>
                            <!-- Shipped -->
                             <li class="relative">
                                <span class="absolute -left-[30px] flex h-6 w-6 items-center justify-center rounded-full 
                                    <?= in_array($order['status'], ['shipped', 'delivered']) ? 'bg-green-500' : 'bg-gray-300' ?> ring-4 ring-white">
                                     <?php if(in_array($order['status'], ['shipped', 'delivered'])): ?>
                                        <i class="fas fa-check text-white text-xs"></i>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <h3 class="font-medium text-gray-900 <?= in_array($order['status'], ['shipped', 'delivered']) ? '' : 'text-gray-500' ?>">Shipped</h3>
                                     <?php if($order['status'] === 'shipped'): ?>
                                        <p class="text-sm text-blue-600">Current Status</p>
                                     <?php endif; ?>
                                </div>
                            </li>
                             <!-- Delivered -->
                             <li class="relative">
                                <span class="absolute -left-[30px] flex h-6 w-6 items-center justify-center rounded-full 
                                    <?= ($order['status'] === 'delivered') ? 'bg-green-500' : 'bg-gray-300' ?> ring-4 ring-white">
                                     <?php if($order['status'] === 'delivered'): ?>
                                        <i class="fas fa-check text-white text-xs"></i>
                                    <?php endif; ?>
                                </span>
                                <div>
                                    <h3 class="font-medium text-gray-900 <?= ($order['status'] === 'delivered') ? '' : 'text-gray-500' ?>">Delivered</h3>
                                     <?php if($order['status'] === 'delivered'): ?>
                                        <p class="text-sm text-green-600">Delivered</p>
                                     <?php endif; ?>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="space-y-6">
                <!-- Order Summary -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Order Summary</h2>
                    <div class="space-y-3 pb-4 border-b border-gray-200">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">$<?= number_format($order['subtotal'] ?? ($order['total'] - $order['shipping_cost'] - $order['tax']), 2) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium">$<?= number_format($order['shipping_cost'], 2) ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Tax</span>
                            <span class="font-medium">$<?= number_format($order['tax'], 2) ?></span>
                        </div>
                        <?php if(!empty($order['discount_amount']) && $order['discount_amount'] > 0): ?>
                             <div class="flex justify-between text-sm text-green-600">
                                <span>Discount</span>
                                <span class="font-medium">-$<?= number_format($order['discount_amount'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="pt-4 flex justify-between items-center">
                        <span class="font-bold text-gray-900">Total</span>
                        <span class="font-bold text-xl text-gray-900">$<?= number_format($order['total'], 2) ?></span>
                    </div>
                     <div class="mt-4 pt-4 border-t border-gray-200">
                         <div class="text-sm text-gray-600 mb-1">Payment Method:</div>
                         <div class="font-medium flex items-center">
                             <i class="fas fa-credit-card text-gray-400 mr-2"></i>
                             <?= ucfirst($order['payment_method']) ?>
                             <span class="ml-2 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full capitalize"><?= str_replace('_', ' ', $order['payment_status']) ?></span>
                         </div>
                     </div>
                </div>

                <!-- Shipping Address -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Shipping Address</h2>
                    <div class="text-sm text-gray-600 leading-relaxed">
                        <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                    </div>
                </div>

                <!-- Support -->
                <div class="bg-gray-50 rounded-lg p-6 border border-gray-200 text-center">
                    <h3 class="font-semibold text-gray-900 mb-2">Need Help?</h3>
                    <p class="text-sm text-gray-600 mb-4">Have issues with this order?</p>
                    <a href="contact.php?order=<?= $orderId ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Contact Support</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
