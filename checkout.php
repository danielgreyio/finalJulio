<?php
require_once 'config/database.php';
require_once 'includes/PaymentGateway.php';
require_once 'includes/CreditCheck.php';
require_once 'includes/OrderProcessor.php';
require_once 'includes/shipping/ShippingService.php';

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
    
    // Shipping cost comes from the carrier quote selected by the buyer.
    // Populated via AJAX call to shipping-quotes.php; default 0 until a quote is chosen.
    // Server enforces non-negative; full server-side re-validation TODO (store quote in session).
    $shippingCost = max(0.0, (float) ($_POST['selected_shipping_cost'] ?? 0));
    $taxAmount = $orderTotal * TAX_RATE;
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
        // Validate shipping address postal code
        $destinationPostal  = trim($_POST['destination_postal'] ?? '');
        $selectedCarrier    = trim($_POST['selected_carrier']    ?? 'standard');
        $selectedService    = trim($_POST['selected_service']    ?? 'standard');

        if (!ShippingService::isValidMexicoPostal($destinationPostal)) {
            $error = "Por favor ingresa un código postal válido de 5 dígitos.";
        }

        if (!isset($error)) {
            // Check credit limit before creating order
            $creditResult = $creditCheck->checkCreditForOrder($_SESSION['user_id'], $orderTotal);

            if (!$creditResult['approved']) {
                $error = "Credit Check Failed: " . $creditResult['message'];
            } else {
            // Create new order
            try {
                $pdo->beginTransaction();

                // Validate and lock stock before creating order
                foreach ($cartItems as $item) {
                    $stockStmt = $pdo->prepare("SELECT id, stock FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
                    $stockStmt->execute([$item['product_id']]);
                    $p = $stockStmt->fetch();
                    if (!$p || $p['stock'] < $item['quantity']) {
                        throw new Exception("Insufficient stock for product {$item['product_id']}");
                    }
                }

                $stmt = $pdo->prepare("
                    INSERT INTO orders (customer_id, total_amount, shipping_cost, tax_amount,
                                       shipping_carrier, shipping_service, destination_postal,
                                       status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_payment', NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'], $orderTotal, $shippingCost, $taxAmount,
                    $selectedCarrier, $selectedService, $destinationPostal,
                ]);
                $orderId = $pdo->lastInsertId();

                // Add order items
                foreach ($cartItems as $item) {
                    $stmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
                }

                // Decrement stock atomically
                foreach ($cartItems as $item) {
                    $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
                }
                
                // If credit was applied, reserve it
                if ($creditResult['credit_applied']) {
                    $reserveResult = $creditCheck->reserveCreditForOrder($_SESSION['user_id'], $orderTotal);
                    if (!$reserveResult['success']) {
                        error_log("Credit reservation failed for user {$_SESSION['user_id']} order $orderId");
                    }
                }

                $pdo->commit();

                // Clear cart
                unset($_SESSION['cart']);

                // Redirect to checkout with order ID
                header("Location: checkout.php?order_id=$orderId");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $error = "Failed to create order: " . $e->getMessage();
            }
            } // end else (credit approved)
        } // end if (!isset($error))
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
    <script src="https://www.paypal.com/sdk/js?client-id=<?= htmlspecialchars($paymentConfig['paypal']['client_id'], ENT_QUOTES, 'UTF-8') ?>&currency=USD"></script>
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
                        <span class="text-gray-600"><?= TAX_LABEL ?></span>
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
                    <!-- Shipping Address + Carrier Selection -->
                    <form method="POST" id="create-order-form">
                        <?= generateCSRFInput() ?>
                        <input type="hidden" name="action" value="create_order">
                        <input type="hidden" name="selected_shipping_cost" id="selected_shipping_cost" value="0">
                        <input type="hidden" name="selected_carrier"       id="selected_carrier"       value="standard">
                        <input type="hidden" name="selected_service"       id="selected_service"       value="standard">

                        <div class="mb-6">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Shipping Address</h3>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Postal Code (CP)</label>
                                    <input type="text"
                                           name="destination_postal"
                                           id="destination_postal"
                                           maxlength="5"
                                           pattern="\d{5}"
                                           placeholder="e.g. 64000"
                                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                           required>
                                    <p class="text-xs text-gray-500 mt-1">5-digit Mexico postal code</p>
                                </div>
                            </div>
                        </div>

                        <!-- Shipping Quotes (populated via JS) -->
                        <div id="shipping-quotes-section" class="mb-6 hidden">
                            <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Choose Shipping</h3>
                            <div id="shipping-quotes-list" class="space-y-2"></div>
                        </div>

                        <div id="shipping-loading" class="hidden text-sm text-gray-500 mb-4">
                            <i class="fas fa-spinner fa-spin mr-2"></i>Getting shipping rates...
                        </div>

                        <div id="shipping-error" class="hidden bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 rounded mb-4"></div>

                        <button type="submit" id="continue-btn"
                                class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            Continue to Payment
                        </button>
                        <p id="select-shipping-hint" class="text-xs text-gray-500 text-center mt-2">Enter your postal code to see shipping options</p>
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
        const stripe = Stripe(<?= json_encode($paymentConfig['stripe']['publishable_key']) ?>);
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

        <?php if ($orderId === 0): ?>
        // Shipping quote lookup
        (function () {
            const postalInput   = document.getElementById('destination_postal');
            const quotesSection = document.getElementById('shipping-quotes-section');
            const quotesList    = document.getElementById('shipping-quotes-list');
            const loadingEl     = document.getElementById('shipping-loading');
            const errorEl       = document.getElementById('shipping-error');
            const continueBtn   = document.getElementById('continue-btn');
            const hint          = document.getElementById('select-shipping-hint');
            const csrfToken     = document.querySelector('input[name="csrf_token"]').value;

            let debounceTimer;

            postalInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const val = this.value.replace(/\D/g, '').slice(0, 5);
                this.value = val;

                if (val.length === 5) {
                    debounceTimer = setTimeout(() => fetchQuotes(val), 600);
                } else {
                    quotesSection.classList.add('hidden');
                    continueBtn.disabled = true;
                    hint.textContent = 'Enter your postal code to see shipping options';
                }
            });

            async function fetchQuotes(postal) {
                loadingEl.classList.remove('hidden');
                errorEl.classList.add('hidden');
                quotesSection.classList.add('hidden');
                continueBtn.disabled = true;

                try {
                    const fd = new FormData();
                    fd.append('csrf_token', csrfToken);
                    fd.append('destination_postal', postal);

                    const res = await fetch('shipping-quotes.php', { method: 'POST', body: fd });
                    const data = await res.json();

                    if (!res.ok || data.error) {
                        showError(data.error || 'Could not get shipping rates. Please try again.');
                        return;
                    }

                    renderQuotes(data.quotes || []);
                } catch (e) {
                    showError('Network error. Please check your connection.');
                } finally {
                    loadingEl.classList.add('hidden');
                }
            }

            function renderQuotes(quotes) {
                quotesList.innerHTML = '';

                if (!quotes.length) {
                    showError('No shipping options available for this postal code.');
                    return;
                }

                quotes.forEach((q, i) => {
                    const days = q.transit_days > 0 ? ` · ${q.transit_days} día${q.transit_days > 1 ? 's' : ''}` : '';
                    const div = document.createElement('label');
                    div.className = 'flex items-center justify-between p-3 border rounded-lg cursor-pointer hover:bg-gray-50';
                    div.innerHTML = `
                        <div class="flex items-center">
                            <input type="radio" name="shipping_quote" value="${i}" class="mr-3" ${i === 0 ? 'checked' : ''}>
                            <span class="font-medium text-sm">${escHtml(q.carrier_label)}${days}</span>
                        </div>
                        <span class="font-semibold text-sm">$${q.price.toFixed(2)} MXN</span>
                    `;
                    div.querySelector('input').addEventListener('change', () => selectQuote(q));
                    quotesList.appendChild(div);
                });

                quotesSection.classList.remove('hidden');
                selectQuote(quotes[0]);
            }

            function selectQuote(q) {
                document.getElementById('selected_shipping_cost').value = q.price.toFixed(2);
                document.getElementById('selected_carrier').value       = q.carrier;
                document.getElementById('selected_service').value       = q.service_code;
                continueBtn.disabled = false;
                hint.textContent = '';
            }

            function showError(msg) {
                errorEl.textContent = msg;
                errorEl.classList.remove('hidden');
            }

            function escHtml(str) {
                return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
        })();
        <?php endif; ?>
    </script>
</body>
</html>