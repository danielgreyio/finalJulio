<?php
require_once 'config/database.php';

// Get cart items from session
$cart = $_SESSION['cart'] ?? [];
$cartDetails = [];
$cartTotal = 0;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = str_repeat('?,', count($ids) - 1) . '?';
    
    // Fetch products
    $stmt = $pdo->prepare("
        SELECT p.* 
        FROM products p 
        WHERE p.id IN ($placeholders)
    ");
    $stmt->execute($ids);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($products as $product) {
        $id = $product['id'];
        if (isset($cart[$id])) {
            $quantity = $cart[$id];
            $subtotal = $product['price'] * $quantity;
            $cartTotal += $subtotal;
            
            $img = !empty($product['image_url']) ? $product['image_url'] : 'assets/images/placeholder.jpg';

            $cartDetails[] = [
                'product_id' => $id,
                'name' => $product['name'],
                'price' => $product['price'],
                'image_url' => $img,
                'quantity' => $quantity,
                'subtotal' => $subtotal,
                'stock' => $product['inventory']
            ];
        }
    }
}

// Update session totals to match
$_SESSION['cart_total'] = $cartTotal;
$_SESSION['cart_count'] = array_sum($cart);
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
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1a5f7a',
                        secondary: '#159895',
                        accent: '#57c5b6',
                        dark: '#002b5b'
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex flex-col min-h-screen">
    <!-- Navbar -->
    <?php include 'includes/navigation.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-gray-800 mb-8">Shopping Cart</h1>

        <?php if (empty($cartDetails)): ?>
            <div class="bg-white rounded-lg shadow-md p-12 text-center">
                <div class="mb-6">
                    <i class="fas fa-shopping-cart text-6xl text-gray-300"></i>
                </div>
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Your cart is empty</h2>
                <p class="text-gray-600 mb-8">Looks like you haven't added any products to your cart yet.</p>
                <a href="search.php" class="inline-block bg-primary hover:bg-dark text-white font-semibold py-3 px-8 rounded-lg transition duration-300">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Cart Items -->
                <div class="lg:w-2/3">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="p-6 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800">Cart Items (<?= $_SESSION['cart_count'] ?>)</h2>
                        </div>
                        <div class="divide-y divide-gray-200">
                            <?php foreach ($cartDetails as $item): ?>
                                <div class="p-6 flex flex-col sm:flex-row items-center gap-6">
                                    <div class="w-full sm:w-24 h-24 flex-shrink-0">
                                        <img src="<?= htmlspecialchars($item['image_url']) ?>" 
                                             alt="<?= htmlspecialchars($item['name']) ?>" 
                                             class="w-full h-full object-cover rounded-md border border-gray-200">
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
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="flex items-center border border-gray-300 rounded-md">
                                            <button onclick="updateQuantity(<?= $item['product_id'] ?>, <?= $item['quantity'] - 1 ?>)" 
                                                    class="px-3 py-1 hover:bg-gray-100 text-gray-600 transition duration-200">
                                                <i class="fas fa-minus text-xs"></i>
                                            </button>
                                            <input type="number" 
                                                   value="<?= $item['quantity'] ?>" 
                                                   min="1" 
                                                   max="<?= $item['stock'] ?>"
                                                   onchange="updateQuantity(<?= $item['product_id'] ?>, this.value)"
                                                   class="w-12 text-center border-x border-gray-300 py-1 text-sm focus:outline-none">
                                            <button onclick="updateQuantity(<?= $item['product_id'] ?>, <?= $item['quantity'] + 1 ?>)" 
                                                    class="px-3 py-1 hover:bg-gray-100 text-gray-600 transition duration-200">
                                                <i class="fas fa-plus text-xs"></i>
                                            </button>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?= $item['stock'] ?> available
                                        </div>
                                    </div>
                                    <div class="text-right min-w-[100px]">
                                        <div class="text-lg font-bold text-gray-800">$<?= number_format($item['subtotal'], 2) ?></div>
                                        <button onclick="removeFromCart(<?= $item['product_id'] ?>)" 
                                                class="text-red-500 hover:text-red-700 text-sm mt-2 transition duration-300">
                                            <i class="fas fa-trash-alt mr-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center mt-6">
                        <a href="search.php" class="text-primary hover:text-dark font-medium transition duration-300 flex items-center">
                            <i class="fas fa-arrow-left mr-2"></i> Continue Shopping
                        </a>
                        <button onclick="clearCart()" class="text-red-500 hover:text-red-700 font-medium transition duration-300 flex items-center">
                            <i class="fas fa-trash-alt mr-2"></i> Clear Cart
                        </button>
                    </div>
                </div>

                <!-- Order Summary -->
                <div class="lg:w-1/3">
                    <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                        <h2 class="text-xl font-semibold text-gray-800 mb-6">Order Summary</h2>
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span>$<?= number_format($cartTotal, 2) ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Shipping estimate</span>
                                <span>Calculated at checkout</span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Tax estimate</span>
                                <span>Calculated at checkout</span>
                            </div>
                            <div class="border-t border-gray-200 pt-4 flex justify-between font-bold text-lg text-gray-800">
                                <span>Total</span>
                                <span>$<?= number_format($cartTotal, 2) ?></span>
                            </div>
                        </div>
                        
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <a href="checkout.php" class="block w-full bg-primary hover:bg-dark text-white text-center font-bold py-3 px-4 rounded-lg transition duration-300 shadow-md">
                                Proceed to Checkout
                            </a>
                        <?php else: ?>
                            <a href="login.php?redirect=checkout.php" class="block w-full bg-primary hover:bg-dark text-white text-center font-bold py-3 px-4 rounded-lg transition duration-300 shadow-md">
                                Login to Checkout
                            </a>
                            <p class="text-center text-sm text-gray-500 mt-2">
                                New customer? <a href="signup.php" class="text-primary hover:underline">Create an account</a>
                            </p>
                        <?php endif; ?>
                        
                        <div class="mt-6 flex items-center justify-center gap-4 text-gray-400 text-2xl">
                            <i class="fab fa-cc-visa hover:text-blue-900 transition duration-300"></i>
                            <i class="fab fa-cc-mastercard hover:text-red-600 transition duration-300"></i>
                            <i class="fab fa-cc-amex hover:text-blue-500 transition duration-300"></i>
                            <i class="fab fa-cc-paypal hover:text-blue-700 transition duration-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12 mt-auto">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-lg font-semibold mb-4">VentDepot</h3>
                    <p class="text-gray-400">Your one-stop shop for all ventilation needs.</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="search.php" class="text-gray-400 hover:text-white transition">Shop all</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white transition">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Customer Service</h3>
                    <ul class="space-y-2">
                        <li><a href="faq.php" class="text-gray-400 hover:text-white transition">FAQ</a></li>
                        <li><a href="shipping.php" class="text-gray-400 hover:text-white transition">Shipping Policy</a></li>
                        <li><a href="returns.php" class="text-gray-400 hover:text-white transition">Returns</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Newsletter</h3>
                    <p class="text-gray-400 mb-4">Subscribe to receive updates, access to exclusive deals, and more.</p>
                    <form class="flex">
                        <input type="email" placeholder="Enter your email" class="px-4 py-2 rounded-l-md w-full text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary">
                        <button type="submit" class="bg-primary hover:bg-dark px-4 py-2 rounded-r-md text-white transition duration-300">Subscribe</button>
                    </form>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; <?= date('Y') ?> VentDepot. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        function updateQuantity(productId, quantity) {
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'update',
                    product_id: productId,
                    quantity: parseInt(quantity)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message);
                }
            })
            .catch((error) => {
                console.error('Error:', error);
            });
        }

        function removeFromCart(productId) {
            if (confirm('Are you sure you want to remove this item?')) {
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
                        location.reload();
                    } else {
                        alert(data.message);
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
            }
        }

        function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
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
                        alert(data.message);
                    }
                })
                .catch((error) => {
                    console.error('Error:', error);
                });
            }
        }
    </script>
</body>
</html>
