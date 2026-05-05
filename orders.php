<?php
session_start();
require_once 'config/database.php';

// Require login - using the existing isLoggedIn() function
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user's orders
$stmt = $pdo->prepare("
    SELECT * FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$orders = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">My Orders</h1>
        
        <?php if (empty($orders)): ?>
            <!-- No Orders -->
            <div class="text-center py-12">
                <i class="fas fa-shopping-bag text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">No orders yet</h3>
                <p class="text-gray-500 mb-6">When you place orders, they'll appear here.</p>
                <a href="search.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <!-- Orders List -->
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
                                        Placed on <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?>
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
                                        <?= nl2br(htmlspecialchars($order['shipping_address'] ?? '')) ?>
                                    </p>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-900 mb-2">Payment Method</h4>
                                    <p class="text-sm text-gray-600">
                                        <i class="<?php
                                            switch($order['payment_method']) {
                                                case 'Stripe':
                                                    echo 'fab fa-cc-stripe text-blue-600';
                                                    break;
                                                case 'PayPal':
                                                    echo 'fab fa-paypal text-blue-500';
                                                    break;
                                                case 'Cash':
                                                    echo 'fas fa-money-bill text-green-600';
                                                    break;
                                                default:
                                                    echo 'fas fa-credit-card text-gray-600';
                                            }
                                        ?> mr-2"></i>
                                        <?= htmlspecialchars($order['payment_method']) ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Order Actions -->
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex space-x-4">
                                    <?php if ($order['status'] === 'shipped'): ?>
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-truck mr-1"></i>Track Package
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['status'] === 'delivered'): ?>
                                        <button class="text-green-600 hover:text-green-800 text-sm font-medium">
                                            <i class="fas fa-star mr-1"></i>Leave Review
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($order['status'], ['delivered', 'cancelled'])): ?>
                                        <button class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            <i class="fas fa-redo mr-1"></i>Reorder
                                        </button>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-2 sm:mt-0 flex space-x-4">
                                    <a href="order-details.php?id=<?= $order['id'] ?>" 
                                       class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View Details
                                    </a>
                                    
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <button onclick="cancelOrder(<?= $order['id'] ?>)"
                                                class="text-red-600 hover:text-red-800 text-sm font-medium">
                                            Cancel Order
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination (if needed) -->
            <div class="mt-8 text-center">
                <p class="text-sm text-gray-600">
                    Showing <?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order?')) {
                return;
            }
            
            fetch('api/orders.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'cancel',
                    order_id: orderId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error cancelling order: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error cancelling order');
            });
        }
    </script>
</body>
</html>