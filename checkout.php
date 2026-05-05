<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'includes/PaymentGateway.php';
require_once 'includes/CreditCheck.php';
require_once 'includes/OrderProcessor.php';
// require_once 'includes/dummy_data.php'; // Removed dummy data

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Get cart items or order ID
$orderId = intval($_GET['order_id'] ?? 0);
$cartItems = [];
$orderTotal = 0;
$shippingCost = 0;
$taxAmount = 0;

if ($orderId > 0) {
    // Load existing order
    $stmt = $pdo->prepare("
        SELECT o.*, oi.*, oi.price_at_purchase as price, p.name as product_name, p.image_url
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN products p ON oi.product_id = p.id
        WHERE o.id = ? AND o.user_id = ? AND o.status = 'pending'
    ");
    $stmt->execute([$orderId, $_SESSION['user_id']]);
    $orderItems = $stmt->fetchAll();
    
    if (empty($orderItems)) {
        header('Location: cart.php');
        exit;
    }
    
    $orderTotal = $orderItems[0]['total'];
    $shippingCost = $orderItems[0]['shipping_cost'];
    $taxAmount = 0; // Tax column is missing in DB
    $cartItems = $orderItems;
} else {
    // Load cart items from DB
    if (empty($_SESSION['cart'])) {
        header('Location: cart.php');
        exit;
    }
    
    // Calculate totals from cart
    $cartItems = [];
    foreach ($_SESSION['cart'] as $productId => $quantity) {
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $itemTotal = $product['price'] * $quantity;
            $orderTotal += $itemTotal;
            // Map DB columns to cart structure
            $cartItems[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'price' => $product['price'],
                'quantity' => $quantity,
                'image_url' => !empty($product['image_url']) ? $product['image_url'] : 'assets/images/placeholder.jpg'
            ];
        }
    }
    
    // Simple Shipping Calculation (Database doesn't have weights/dims yet)
    // Flat rate $10 + $5 per item for now as per project requirements
    $shippingBaseRate = 10.00;
    $shippingPerItem = 5.00;
    
    $shippingCost = $shippingBaseRate + (count($cartItems) * $shippingPerItem);
    
    // Tax Calculation
    $taxAmount = $orderTotal * 0.08; // 8% tax
    $orderTotal += $shippingCost + $taxAmount;
}

// Initialize payment gateway, credit check and order processor
$paymentGateway = new PaymentGateway($pdo);
$creditCheck = new CreditCheck($pdo);
$orderProcessor = new OrderProcessor($pdo);
$paymentConfig = $paymentGateway->getPaymentMethodsConfig();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || !Security::validateCSRFToken($_POST['csrf_token'])) {
        die("CSRF Token Validation Failed");
    }
    
    $action = $_POST['action'];
    
    if ($action === 'create_order' && $orderId === 0) {
        // Collect shipping info
        $shippingInfo = [
           'address' => $_POST['address'] ?? '',
           'city' => $_POST['city'] ?? '',
           'zip' => $_POST['zip'] ?? ''
        ];

        // Check credit limit before creating order
        $creditResult = $creditCheck->checkCreditForOrder($_SESSION['user_id'], $orderTotal);
        
        if (!$creditResult['approved']) {
            $error = "Credit Check Failed: " . $creditResult['message'];
        } else {
            // Create new order
            // Note: We pass $cartItems (structure with product_id, quantity) to OrderProcessor
            $result = $orderProcessor->createOrder($_SESSION['user_id'], $cartItems, $shippingInfo);

            if ($result['success']) {
                $orderId = $result['order_id'];
                
                // If credit was applied, reserve it
                if ($creditResult['credit_applied']) {
                    $reserveResult = $creditCheck->reserveCreditForOrder($_SESSION['user_id'], $orderTotal);
                    if (!$reserveResult['success']) {
                         // Warning: Credit reservation failed but order created. 
                         // In production, we should log this critical error.
                    }
                }
                
                // Clear cart
                unset($_SESSION['cart']);
                
                // Redirect to checkout with order ID
                header("Location: checkout.php?order_id=$orderId");
                exit;
                
            } else {
                $error = "Failed to create order: " . $result['error'];
            }
        }
    }
    
    if ($action === 'process_payment' && $orderId > 0) {
        $paymentMethod = $_POST['payment_method'] ?? '';
        $paymentData = [];
        
        if ($paymentMethod === 'stripe') {
            $paymentData['payment_method_id'] = $_POST['stripe_payment_method_id'] ?? '';
        } elseif ($paymentMethod === 'paypal') {
            $paymentData['paypal_order_id'] = $_POST['paypal_order_id'] ?? '';
        } elseif ($paymentMethod === 'mock') {
            $paymentData['card_number'] = $_POST['card_number'] ?? '';
            $paymentData['expiry'] = $_POST['expiry'] ?? '';
        }
        
        $result = $paymentGateway->processPayment($orderId, $paymentMethod, $paymentData);
        
        if ($result['success']) {
            // Redirect to success page
            header("Location: order-confirmation.php?order_id=$orderId&transaction_id=" . $result['transaction_id']);
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Stripe.js -->
    <?php if ($paymentConfig['stripe']['enabled']): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    
    <!-- PayPal SDK -->
    <?php if ($paymentConfig['paypal']['enabled']): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?= $paymentConfig['paypal']['client_id'] ?>&currency=USD"></script>
    <?php endif; ?>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Order Summary</h2>
                
                <div class="space-y-4 mb-6">
                    <?php foreach ($cartItems as $item): ?>
                        <div class="flex items-center space-x-4">
                            <img src="<?= htmlspecialchars($item['image_url'] ?: 'https://via.placeholder.com/80x80') ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 class="w-16 h-16 object-cover rounded-lg">
                            <div class="flex-1">
                                <h3 class="font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></h3>
                                <p class="text-gray-600">Quantity: <?= $item['quantity'] ?></p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium">$<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="border-t pt-4 space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span>$<?= number_format($orderTotal - $shippingCost - $taxAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping</span>
                        <span>$<?= number_format($shippingCost, 2) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax</span>
                        <span>$<?= number_format($taxAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between font-semibold text-lg border-t pt-2">
                        <span>Total</span>
                        <span>$<?= number_format($orderTotal, 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Payment Information</h2>
                
                <?php if (isset($error)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($orderId === 0): ?>
                    <!-- Create Order Form -->
                    <form method="POST" id="create-order-form" class="space-y-4">
                        <?= Security::getCSRFInput() ?>
                        <input type="hidden" name="action" value="create_order">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Shipping Address</label>
                            <input type="text" name="address" required placeholder="123 Main St"
                                   class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">City</label>
                                <input type="text" name="city" required placeholder="New York"
                                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">ZIP</label>
                                <input type="text" name="zip" required placeholder="10001"
                                       class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm p-2 focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors">
                            Continue to Payment
                        </button>
                    </form>
                <?php else: ?>
                    <!-- Payment Methods -->
                    <div class="space-y-6">
                        <!-- Payment Method Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">Choose Payment Method</label>
                            <div class="space-y-3">
                                <?php if ($paymentConfig['stripe']['enabled']): ?>
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="stripe" class="mr-3" checked>
                                        <div class="flex items-center">
                                            <i class="fab fa-cc-stripe text-2xl text-blue-600 mr-3"></i>
                                            <div>
                                                <div class="font-medium">Credit/Debit Card</div>
                                                <div class="text-sm text-gray-600">Visa, Mastercard, American Express</div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endif; ?>
                                
                                <?php if ($paymentConfig['paypal']['enabled']): ?>
                                    <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                        <input type="radio" name="payment_method" value="paypal" class="mr-3">
                                        <div class="flex items-center">
                                            <i class="fab fa-paypal text-2xl text-blue-700 mr-3"></i>
                                            <div>
                                                <div class="font-medium">PayPal</div>
                                                <div class="text-sm text-gray-600">Pay with your PayPal account</div>
                                            </div>
                                        </div>
                                    </label>
                                <?php endif; ?>

                                <!-- Mock Option -->
                                <label class="flex items-center p-4 border rounded-lg cursor-pointer hover:bg-gray-50">
                                    <input type="radio" name="payment_method" value="mock" class="mr-3">
                                    <div class="flex items-center">
                                        <i class="fas fa-flask text-2xl text-purple-600 mr-3"></i>
                                        <div>
                                            <div class="font-medium">Test Credit Card</div>
                                            <div class="text-sm text-gray-600">For testing purposes only</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Stripe Payment Form -->
                        <?php if ($paymentConfig['stripe']['enabled']): ?>
                            <div id="stripe-payment" class="payment-form">
                                <form id="stripe-payment-form" method="POST">
                                    <?= Security::getCSRFInput() ?>
                                    <input type="hidden" name="action" value="process_payment">
                                    <input type="hidden" name="payment_method" value="stripe">
                                    <input type="hidden" name="stripe_payment_method_id" id="stripe-payment-method-id">
                                    
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Card Information</label>
                                        <div id="stripe-card-element" class="p-3 border rounded-lg">
                                            <!-- Stripe Elements will create form elements here -->
                                        </div>
                                        <div id="stripe-card-errors" class="text-red-600 text-sm mt-2"></div>
                                    </div>
                                    
                                    <button type="submit" id="stripe-submit-button" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50">
                                        <span id="stripe-button-text">Pay $<?= number_format($orderTotal, 2) ?></span>
                                        <span id="stripe-spinner" class="hidden">
                                            <i class="fas fa-spinner fa-spin mr-2"></i>Processing...
                                        </span>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <!-- PayPal Payment -->
                        <?php if ($paymentConfig['paypal']['enabled']): ?>
                            <div id="paypal-payment" class="payment-form hidden">
                                <div id="paypal-button-container"></div>
                                <form id="paypal-payment-form" method="POST" class="hidden">
                                     <?= Security::getCSRFInput() ?>
                                    <input type="hidden" name="action" value="process_payment">
                                    <input type="hidden" name="payment_method" value="paypal">
                                    <input type="hidden" name="paypal_order_id" id="paypal-order-id">
                                </form>
                            </div>
                            </div>
                        <?php endif; ?>

                        <!-- Mock Payment -->
                        <div id="mock-payment" class="payment-form hidden">
                            <form id="mock-payment-form" method="POST">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="action" value="process_payment">
                                <input type="hidden" name="payment_method" value="mock">
                                
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                                    <p class="text-sm text-yellow-800">
                                        <i class="fas fa-info-circle mr-2"></i>
                                        Test Mode: Use card <strong>1231231231231233</strong>
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Card Number</label>
                                        <input type="text" name="card_number" required placeholder="1231231231231233"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Expiry</label>
                                        <input type="text" name="expiry" required placeholder="MM/YY"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">CVC</label>
                                        <input type="text" name="cvc" required placeholder="123"
                                            class="w-full px-4 py-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                                    </div>
                                </div>

                                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors mt-6">
                                    Pay $<?= number_format($orderTotal, 2) ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Security Notice -->
                    <div class="mt-8 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-lock mr-2"></i>
                            Your payment information is encrypted and secure. We never store your credit card details.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Payment method switching
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.payment-form').forEach(form => {
                    form.classList.add('hidden');
                });
                
                if (this.value === 'stripe') {
                    document.getElementById('stripe-payment').classList.remove('hidden');
                } else if (this.value === 'paypal') {
                    document.getElementById('paypal-payment').classList.remove('hidden');
                } else if (this.value === 'mock') {
                    document.getElementById('mock-payment').classList.remove('hidden');
                }
            });
        });

        <?php if ($paymentConfig['stripe']['enabled'] && $orderId > 0): ?>
        // Initialize Stripe
        const stripe = Stripe('<?= $paymentConfig['stripe']['publishable_key'] ?>');
        const elements = stripe.elements();
        
        const cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#424770',
                    '::placeholder': {
                        color: '#aab7c4',
                    },
                },
            },
        });
        
        cardElement.mount('#stripe-card-element');
        
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('stripe-card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        const stripeForm = document.getElementById('stripe-payment-form');
        stripeForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            const submitButton = document.getElementById('stripe-submit-button');
            const buttonText = document.getElementById('stripe-button-text');
            const spinner = document.getElementById('stripe-spinner');
            
            submitButton.disabled = true;
            buttonText.classList.add('hidden');
            spinner.classList.remove('hidden');
            
            const {paymentMethod, error} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });
            
            if (error) {
                document.getElementById('stripe-card-errors').textContent = error.message;
                submitButton.disabled = false;
                buttonText.classList.remove('hidden');
                spinner.classList.add('hidden');
            } else {
                document.getElementById('stripe-payment-method-id').value = paymentMethod.id;
                stripeForm.submit();
            }
        });
        <?php endif; ?>

        <?php if ($paymentConfig['paypal']['enabled'] && $orderId > 0): ?>
        // Initialize PayPal
        paypal.Buttons({
            createOrder: function(data, actions) {
                return actions.order.create({
                    purchase_units: [{
                        amount: {
                            value: '<?= number_format($orderTotal, 2, '.', '') ?>'
                        }
                    }]
                });
            },
            onApprove: function(data, actions) {
                return actions.order.capture().then(function(details) {
                    document.getElementById('paypal-order-id').value = data.orderID;
                    document.getElementById('paypal-payment-form').submit();
                });
            },
            onError: function(err) {
                console.error('PayPal Error:', err);
                alert('An error occurred with PayPal. Please try again.');
            }
        }).render('#paypal-button-container');
        <?php endif; ?>
    </script>
</body>
</html>