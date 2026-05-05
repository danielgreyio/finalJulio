<?php
require_once 'config/database.php';

// Get cart items with product details
$cart = getCartItems();
$cartDetails = [];
$cartTotal = 0;

if (!empty($cart)) {
    $productIds = array_keys($cart);
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
    $stmt->execute($productIds);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($products as $product) {
        $quantity = $cart[$product['id']];
        $subtotal = $product['price'] * $quantity;
        $cartTotal += $subtotal;
        
        $cartDetails[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-8">Shopping Cart</h1>
        
        <?php if (empty($cartDetails)): ?>
            <!-- Empty Cart -->
            <div class="text-center py-12">
                <i class="fas fa-shopping-cart text-6xl text-gray-300 mb-4"></i>
                <h3 class="text-xl font-semibold text-gray-600 mb-2">Your cart is empty</h3>
                <p class="text-gray-500 mb-6">Add some products to get started!</p>
                <a href="search.php" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                    Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">Cart Items (<?= count($cartDetails) ?>)</h2>
                        </div>
                        
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($cartDetails as $item): ?>
                                <div class="p-6 flex items-center space-x-4" id="cart-item-<?= $item['product']['id'] ?>">
                                    <!-- Product Image -->
                                    <div class="flex-shrink-0">
                                        <img src="<?= htmlspecialchars($item['product']['image_url'] ?? 'https://via.placeholder.com/100x100') ?>" 
                                             alt="<?= htmlspecialchars($item['product']['name']) ?>"
                                             class="w-20 h-20 object-cover rounded-lg">
                                    </div>
                                    
                                    <!-- Product Details -->
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-semibold text-gray-900">
                                            <a href="product.php?id=<?= $item['product']['id'] ?>" class="hover:text-blue-600">
                                                <?= htmlspecialchars($item['product']['name']) ?>
                                            </a>
                                        </h3>
                                        <p class="text-gray-600 text-sm mt-1">
                                            <?= htmlspecialchars(substr($item['product']['description'], 0, 100)) ?>...
                                        </p>
                                        <p class="text-blue-600 font-semibold mt-2">
                                            $<?= number_format($item['product']['price'], 2) ?>
                                            <?php if (!empty($item['product']['unit_of_measure'])): ?>
                                                <span class="text-gray-400 font-normal text-sm">/ <?= htmlspecialchars($item['product']['unit_of_measure']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    
                                    <!-- Quantity Controls -->
                                    <div class="flex items-center space-x-3">
                                        <button onclick="updateQuantity(<?= $item['product']['id'] ?>, <?= $item['quantity'] - 1 ?>)"
                                                class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center">
                                            <i class="fas fa-minus text-sm"></i>
                                        </button>
                                        
                                        <span class="w-12 text-center font-semibold" id="quantity-<?= $item['product']['id'] ?>">
                                            <?= $item['quantity'] ?>
                                        </span>
                                        
                                        <button onclick="updateQuantity(<?= $item['product']['id'] ?>, <?= $item['quantity'] + 1 ?>)"
                                                class="w-8 h-8 rounded-full bg-gray-200 hover:bg-gray-300 flex items-center justify-center">
                                            <i class="fas fa-plus text-sm"></i>
                                        </button>
                                    </div>
                                    
                                    <!-- Subtotal -->
                                    <div class="text-right">
                                        <p class="text-lg font-semibold text-gray-900" id="subtotal-<?= $item['product']['id'] ?>">
                                            $<?= number_format($item['subtotal'], 2) ?>
                                        </p>
                                        <button onclick="removeFromCart(<?= $item['product']['id'] ?>)"
                                                class="text-red-600 hover:text-red-800 text-sm mt-1">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Cart Actions -->
                        <div class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                            <button onclick="clearCart()" class="text-gray-600 hover:text-gray-800">
                                <i class="fas fa-trash"></i> Clear Cart
                            </button>
                            <a href="search.php" class="text-blue-600 hover:text-blue-800">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                        <h2 class="text-lg font-semibold mb-4">Order Summary</h2>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="font-semibold" id="cart-subtotal">$<?= number_format($cartTotal, 2) ?></span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="text-gray-600">Shipping</span>
                                <span class="text-sm text-gray-500">Calculated at checkout</span>
                            </div>
                            
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax</span>
                                <span class="text-sm text-gray-500">Calculated at checkout</span>
                            </div>
                            
                            <hr class="my-4">
                            
                            <div class="flex justify-between text-lg font-semibold">
                                <span>Total</span>
                                <span id="cart-total">$<?= number_format($cartTotal, 2) ?></span>
                            </div>
                        </div>
                        
                        <div class="mt-6 space-y-3">
                            <?php if (isLoggedIn()): ?>
                                <a href="checkout.php" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 block text-center">
                                    Proceed to Checkout
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=checkout.php" class="w-full bg-blue-600 text-white py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 block text-center">
                                    Login to Checkout
                                </a>
                                <p class="text-sm text-gray-600 text-center">
                                    Don't have an account? 
                                    <a href="register.php" class="text-blue-600 hover:text-blue-800">Sign up</a>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Security Badge -->
                        <div class="mt-6 text-center">
                            <div class="flex items-center justify-center space-x-2 text-sm text-gray-600">
                                <i class="fas fa-lock"></i>
                                <span>Secure checkout</span>
                            </div>
                            <div class="flex items-center justify-center space-x-4 mt-2">
                                <i class="fab fa-cc-visa text-2xl text-blue-600"></i>
                                <i class="fab fa-cc-mastercard text-2xl text-red-600"></i>
                                <i class="fab fa-paypal text-2xl text-blue-500"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateQuantity(productId, newQuantity) {
            if (newQuantity < 0) return;
            
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update',
                    product_id: productId,
                    quantity: newQuantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (newQuantity === 0) {
                        document.getElementById(`cart-item-${productId}`).remove();
                        if (data.cart_count === 0) {
                            location.reload();
                        }
                    } else {
                        document.getElementById(`quantity-${productId}`).textContent = newQuantity;
                        // Update subtotal (you'd need to fetch the price)
                        location.reload(); // Simple reload for now
                    }
                    updateCartTotals(data.cart_total);
                } else {
                    alert('Error updating cart: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating cart');
            });
        }
        
        function removeFromCart(productId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }
            
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'remove',
                    product_id: productId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`cart-item-${productId}`).remove();
                    updateCartTotals(data.cart_total);
                    if (data.cart_count === 0) {
                        location.reload();
                    }
                } else {
                    alert('Error removing item: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error removing item');
            });
        }
        
        function clearCart() {
            if (!confirm('Are you sure you want to clear your entire cart?')) {
                return;
            }
            
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'clear'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error clearing cart: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error clearing cart');
            });
        }
        
        function updateCartTotals(newTotal) {
            document.getElementById('cart-subtotal').textContent = '$' + newTotal.toFixed(2);
            document.getElementById('cart-total').textContent = '$' + newTotal.toFixed(2);
        }
    </script>
</body>
</html>
