<?php
require_once 'config/database.php';
require_once 'includes/Mailer.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$orderId = intval($_GET['order_id'] ?? 0);
$transactionId = intval($_GET['transaction_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: index.php');
    exit;
}

// Get order details
$stmt = $pdo->prepare("
    SELECT o.*, pt.gateway_reference, pt.payment_method, pt.amount as payment_amount,
           u.email as customer_email
    FROM orders o

    LEFT JOIN payment_transactions pt ON o.id = pt.order_id
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->execute([$orderId, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
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

// Generate order confirmation number
$confirmationNumber = 'VD-' . str_pad($orderId, 6, '0', STR_PAD_LEFT) . '-' . date('Y', strtotime($order['created_at']));

// Send confirmation email once per order (session guard prevents duplicate sends on refresh)
$emailKey = 'order_confirm_sent_' . $orderId;
if (empty($_SESSION[$emailKey])) {
    $mailer = new Mailer();
    $mailer->sendOrderConfirmation($order['customer_email'], $order['customer_email'], $order);
    $_SESSION[$emailKey] = true;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Success Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                <i class="fas fa-check text-green-600 text-2xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">Order Confirmed!</h1>
            <p class="text-gray-600">Thank you for your purchase. Your order has been received and is being processed.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Order Details -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Order Details</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order Number</span>
                        <span class="font-medium"><?= $confirmationNumber ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Order Date</span>
                        <span class="font-medium"><?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Payment Method</span>
                        <span class="font-medium capitalize">
                            <?php if ($order['payment_method'] === 'stripe'): ?>
                                <i class="fab fa-cc-stripe mr-1"></i> Credit Card
                            <?php elseif ($order['payment_method'] === 'paypal'): ?>
                                <i class="fab fa-paypal mr-1"></i> PayPal
                            <?php else: ?>
                                <?= ucfirst($order['payment_method'] ?? 'N/A') ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <?php if ($order['gateway_reference']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Transaction ID</span>
                            <span class="font-medium text-sm"><?= htmlspecialchars($order['gateway_reference']) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Status</span>
                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full 
                            <?= $order['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= ucfirst(str_replace('_', ' ', $order['status'])) ?>
                        </span>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="mt-8">
                    <h3 class="font-semibold text-gray-900 mb-4">Items Ordered</h3>
                    <div class="space-y-4">
                        <?php foreach ($orderItems as $item): ?>
                            <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                                <img src="<?= htmlspecialchars($item['image_url'] ?: 'https://via.placeholder.com/80x80') ?>" 
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     class="w-16 h-16 object-cover rounded-lg">
                                <div class="flex-1">
                                    <h4 class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></h4>
                                    <p class="text-sm text-gray-600">Sold by: <?= htmlspecialchars($item['merchant_email']) ?></p>
                                    <p class="text-sm text-gray-600">Quantity: <?= $item['quantity'] ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium">$<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                                    <p class="text-sm text-gray-600">$<?= number_format($item['price'], 2) ?> each</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Payment Summary</h2>
                
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span>$<?= number_format($order['total'] - $order['shipping_cost'], 2) ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping</span>
                        <span>$<?= number_format($order['shipping_cost'], 2) ?></span>
                    </div>
                    
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax</span>
                        <span>$0.00</span>
                    </div>
                    
                    <div class="border-t pt-3">
                        <div class="flex justify-between font-semibold text-lg">
                            <span>Total Paid</span>
                            <span>$<?= number_format($order['total'], 2) ?></span>
                        </div>
                    </div>
                </div>

                <!-- What's Next -->
                <div class="mt-8">
                    <h3 class="font-semibold text-gray-900 mb-4">What's Next?</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-start">
                            <i class="fas fa-envelope text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-900">Order Confirmation</p>
                                <p>We've sent a confirmation email to <?= htmlspecialchars($order['customer_email']) ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <i class="fas fa-box text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-900">Processing</p>
                                <p>Your order is being prepared by the merchant(s)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <i class="fas fa-truck text-blue-600 mr-3 mt-1"></i>
                            <div>
                                <p class="font-medium text-gray-900">Shipping</p>
                                <p>You'll receive tracking information once your order ships</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 space-y-3">
                    <a href="order-details.php?id=<?= $orderId ?>" 
                       class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition-colors inline-block text-center">
                        View Order Details
                    </a>
                    
                    <a href="index.php" 
                       class="w-full bg-gray-200 text-gray-800 py-2 px-4 rounded-lg hover:bg-gray-300 transition-colors inline-block text-center">
                        Continue Shopping
                    </a>
                </div>
            </div>
        </div>

        <!-- Customer Support -->
        <div class="mt-8 bg-white rounded-lg shadow-md p-6">
            <div class="text-center">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Need Help?</h3>
                <p class="text-gray-600 mb-4">If you have any questions about your order, please don't hesitate to contact us.</p>
                <div class="flex justify-center space-x-4">
                    <a href="contact.php" class="text-blue-600 hover:text-blue-700">
                        <i class="fas fa-envelope mr-1"></i> Contact Support
                    </a>
                    <a href="faq.php" class="text-blue-600 hover:text-blue-700">
                        <i class="fas fa-question-circle mr-1"></i> FAQ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Track order confirmation event (for analytics)
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                'transaction_id': '<?= $confirmationNumber ?>',
                'value': <?= $order['total'] ?>,
                'currency': 'USD',
                'items': [
                    <?php foreach ($orderItems as $index => $item): ?>
                    {
                        'item_id': '<?= $item['product_id'] ?>',
                        'item_name': '<?= addslashes($item['product_name']) ?>',
                        'quantity': <?= $item['quantity'] ?>,
                        'price': <?= $item['price'] ?>
                    }<?= $index < count($orderItems) - 1 ? ',' : '' ?>
                    <?php endforeach; ?>
                ]
            });
        }
    </script>
</body>
</html>