<?php
/**
 * Payment Gateway Integration System
 * Handles Stripe and PayPal payment processing for marketplace transactions
 */

require_once 'security.php';
require_once __DIR__ . '/CreditCheck.php';

class PaymentGateway {
    private $pdo;
    private $stripeSecretKey;
    private $stripePublishableKey;
    private $paypalClientId;
    private $paypalClientSecret;
    private $paypalMode; // 'sandbox' or 'live'
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->creditCheck = new CreditCheck($pdo);
        $this->loadConfiguration();
    }
    
    /**
     * Load payment gateway configuration
     */
    private function loadConfiguration() {
        // These should be loaded from environment variables or secure config
        $this->stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'] ?? 'sk_test_dummy';
        $this->stripePublishableKey = $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? 'pk_test_dummy';
        // Default to 'sb' (PayPal Sandbox) if not set, to enable the feature for demo
        $this->paypalClientId = $_ENV['PAYPAL_CLIENT_ID'] ?? 'sb'; 
        $this->paypalClientSecret = $_ENV['PAYPAL_CLIENT_SECRET'] ?? 'secret';
        $this->paypalMode = $_ENV['PAYPAL_MODE'] ?? 'sandbox';
    }
    
    /**
     * Process payment through selected gateway
     */
    public function processPayment($orderId, $paymentMethod, $paymentData) {
        try {
            $this->pdo->beginTransaction();
            
            // Get order details
            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }
            
            // Validate order status
            if ($order['status'] !== 'pending_payment' && $order['status'] !== 'pending') {
                throw new Exception("Order is not in a payable state (Status: " . $order['status'] . ")");
            }
            
            $result = null;
            
            switch ($paymentMethod) {
                case 'stripe':
                    $result = $this->processStripePayment($order, $paymentData);
                    break;
                    
                case 'paypal':
                    $result = $this->processPayPalPayment($order, $paymentData);
                    break;
                    
                case 'mock':
                    $result = $this->processMockPayment($order, $paymentData);
                    break;
                    
                default:
                    throw new Exception("Unsupported payment method");
            }
            
            if ($result['success']) {
                $transactionId = 0;
                
                // Check if this is a mock payment
                $isMock = false;
                if (isset($result['raw_response'])) {
                    $raw = json_decode($result['raw_response'], true);
                    if (isset($raw['mock']) && $raw['mock'] === true) {
                        $isMock = true;
                        $transactionId = 123456; // Dummy Transaction ID
                    }
                }

                if (!$isMock) {
                    // Record payment transaction
                    $transactionId = $this->recordTransaction($orderId, $paymentMethod, $result);
                    
                    // Update order status
                    $this->updateOrderStatus($orderId, 'paid', $transactionId);
                    
                    // Process commission splits
                    $this->processCommissionSplits($orderId, $result['net_amount']);
                    
                    // Release credit if it was reserved
                    $this->releaseCreditIfReserved($orderId, $order['user_id'], $order['total']);
                    
                    // Create accounts receivable entry with credit information
                    $this->createAccountsReceivableEntry($orderId, $order);
                    
                    $this->pdo->commit();
                } else {
                    // Update order status even for mock, so confirmation page shows "Paid"
                    // We assume 'orders' table exists if getOrderDetails succeeded.
                    try {
                        $this->updateOrderStatus($orderId, 'paid', $transactionId);
                    } catch (Exception $e) {
                        // Ignore DB errors in mock mode
                    }

                    if ($this->pdo->inTransaction()) {
                        $this->pdo->commit();
                    }
                }
                
                return [
                    'success' => true,
                    'transaction_id' => $transactionId,
                    'gateway_reference' => $result['gateway_reference'],
                    'message' => 'Payment processed successfully'
                ];
            } else {
                throw new Exception($result['error_message'] ?? 'Payment processing failed');
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            // Log payment failure
            Security::logSecurityEvent('payment_failure', [
                'order_id' => $orderId,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage()
            ], 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process Stripe payment
     */
    private function processStripePayment($order, $paymentData) {
        try {
            // Initialize Stripe (this is a simplified version - you'd need the actual Stripe PHP SDK)
            $stripe = [
                'secret_key' => $this->stripeSecretKey,
                'publishable_key' => $this->stripePublishableKey
            ];
            
            // Calculate amounts (in cents for Stripe)
            $totalAmount = round($order['total'] * 100);
            $platformFee = round($order['total'] * 0.029 * 100); // 2.9% platform fee
            
            // Create payment intent or charge
            $paymentIntent = $this->createStripePaymentIntent([
                'amount' => $totalAmount,
                'currency' => 'usd',
                'payment_method' => $paymentData['payment_method_id'],
                'confirm' => true,
                'metadata' => [
                    'order_id' => $order['id'],
                    'customer_id' => $order['user_id'],
                    'merchant_id' => $order['merchant_id']
                ],
                'application_fee_amount' => $platformFee
            ]);
            
            if ($paymentIntent['status'] === 'succeeded') {
                return [
                    'success' => true,
                    'gateway_reference' => $paymentIntent['id'],
                    'net_amount' => ($totalAmount - $platformFee) / 100,
                    'platform_fee' => $platformFee / 100,
                    'gateway_fee' => 0, // Included in platform fee
                    'raw_response' => json_encode($paymentIntent)
                ];
            } else {
                return [
                    'success' => false,
                    'error_message' => 'Payment requires additional authentication or failed'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process PayPal payment
     */
    private function processPayPalPayment($order, $paymentData) {
        try {
            // MOCK: If using the 'sb' dummy ID, return success immediately
            if ($this->paypalClientId === 'sb') {
                $totalAmount = number_format($order['total'], 2, '.', '');
                $platformFee = number_format($order['total'] * 0.029, 2, '.', '');
                $merchantAmount = number_format($order['total'] - $platformFee, 2, '.', '');
                
                return [
                    'success' => true,
                    'gateway_reference' => 'mock_pp_' . uniqid(),
                    'net_amount' => floatval($merchantAmount),
                    'platform_fee' => floatval($platformFee),
                    'gateway_fee' => 0,
                    'raw_response' => json_encode(['status' => 'COMPLETED', 'mock' => true])
                ];
            }

            // Get PayPal access token
            $accessToken = $this->getPayPalAccessToken();
            
            if (!$accessToken) {
                throw new Exception("Failed to get PayPal access token");
            }
            
            // Calculate amounts
            $totalAmount = number_format($order['total'], 2, '.', '');
            $platformFee = number_format($order['total'] * 0.029, 2, '.', '');
            $merchantAmount = number_format($order['total'] - $platformFee, 2, '.', '');
            
            // Create PayPal order
            $paypalOrder = $this->createPayPalOrder([
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'reference_id' => 'order_' . $order['id'],
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => $totalAmount
                        ],
                        'payee' => [
                            'merchant_id' => $order['merchant_paypal_id'] ?? null
                        ],
                        'payment_instruction' => [
                            'platform_fees' => [
                                [
                                    'amount' => [
                                        'currency_code' => 'USD',
                                        'value' => $platformFee
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ], $accessToken);
            
            if ($paypalOrder && $paypalOrder['status'] === 'COMPLETED') {
                return [
                    'success' => true,
                    'gateway_reference' => $paypalOrder['id'],
                    'net_amount' => floatval($merchantAmount),
                    'platform_fee' => floatval($platformFee),
                    'gateway_fee' => 0,
                    'raw_response' => json_encode($paypalOrder)
                ];
            } else {
                return [
                    'success' => false,
                    'error_message' => 'PayPal payment failed or requires approval'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error_message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Record payment transaction
     */
    private function recordTransaction($orderId, $paymentMethod, $result) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_transactions (
                order_id, payment_method, gateway_reference, amount, 
                platform_fee, gateway_fee, net_amount, status, 
                raw_response, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
        ");
        
        $stmt->execute([
            $orderId,
            $paymentMethod,
            $result['gateway_reference'],
            $result['net_amount'] + $result['platform_fee'],
            $result['platform_fee'],
            $result['gateway_fee'],
            $result['net_amount'],
            $result['raw_response']
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Process commission splits between platform and merchant
     */
    private function processCommissionSplits($orderId, $netAmount) {
        $order = $this->getOrderDetails($orderId);
        
        // Create commission record for merchant
        $stmt = $this->pdo->prepare("
            INSERT INTO merchant_commissions (
                order_id, merchant_id, gross_amount, commission_rate, 
                commission_amount, net_amount, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending_payout', NOW())
        ");
        
        $grossAmount = $order['total'];
        $commissionRate = 0.05; // 5% commission
        $commissionAmount = $grossAmount * $commissionRate;
        $merchantNetAmount = $grossAmount - $commissionAmount;
        
        $stmt->execute([
            $orderId,
            $order['merchant_id'],
            $grossAmount,
            $commissionRate,
            $commissionAmount,
            $merchantNetAmount
        ]);
    }
    
    /**
     * Get order details
     */
    private function getOrderDetails($orderId) {
        $stmt = $this->pdo->prepare("
            SELECT o.*, u.email as customer_email, m.email as merchant_email
            FROM orders o
            JOIN users u ON o.user_id = u.id
            JOIN users m ON o.merchant_id = m.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetch();
    }
    
    /**
     * Update order status
     */
    private function updateOrderStatus($orderId, $status, $transactionId = null) {
        $stmt = $this->pdo->prepare("
            UPDATE orders 
            SET status = ?, payment_transaction_id = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$status, $transactionId, $orderId]);
    }
    
    /**
     * Create Stripe Payment Intent (simplified version)
     */
    private function createStripePaymentIntent($data) {
        // This is a simplified simulation - you'd use the actual Stripe PHP SDK
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.stripe.com/v1/payment_intents",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $this->stripeSecretKey,
                "Content-Type: application/x-www-form-urlencoded"
            ],
            CURLOPT_POSTFIELDS => http_build_query($data)
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    /**
     * Get PayPal access token
     */
    private function getPayPalAccessToken() {
        $baseUrl = $this->paypalMode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . "/v1/oauth2/token",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Accept-Language: en_US"
            ],
            CURLOPT_USERPWD => $this->paypalClientId . ":" . $this->paypalClientSecret,
            CURLOPT_POSTFIELDS => "grant_type=client_credentials"
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }
    
    /**
     * Create PayPal order
     */
    private function createPayPalOrder($orderData, $accessToken) {
        $baseUrl = $this->paypalMode === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $baseUrl . "/v2/checkout/orders",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json",
                "Authorization: Bearer " . $accessToken
            ],
            CURLOPT_POSTFIELDS => json_encode($orderData)
        ]);
        
        $response = curl_exec($curl);
        curl_close($curl);
        
        return json_decode($response, true);
    }
    
    /**
     * Process refund
     */
    public function processRefund($transactionId, $amount = null, $reason = '') {
        try {
            $transaction = $this->getTransaction($transactionId);
            
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }
            
            $refundAmount = $amount ?? $transaction['amount'];
            
            switch ($transaction['payment_method']) {
                case 'stripe':
                    $result = $this->processStripeRefund($transaction, $refundAmount, $reason);
                    break;
                    
                case 'paypal':
                    $result = $this->processPayPalRefund($transaction, $refundAmount, $reason);
                    break;
                    
                default:
                    throw new Exception("Unsupported payment method for refund");
            }
            
            if ($result['success']) {
                // Record refund transaction
                $this->recordRefund($transactionId, $refundAmount, $result['refund_id'], $reason);
                
                return [
                    'success' => true,
                    'refund_id' => $result['refund_id'],
                    'amount' => $refundAmount
                ];
            } else {
                throw new Exception($result['error_message']);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get transaction details
     */
    private function getTransaction($transactionId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM payment_transactions WHERE id = ?
        ");
        $stmt->execute([$transactionId]);
        return $stmt->fetch();
    }
    
    /**
     * Record refund
     */
    private function recordRefund($transactionId, $amount, $refundId, $reason) {
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_refunds (
                transaction_id, amount, gateway_refund_id, reason, 
                status, created_at
            ) VALUES (?, ?, ?, ?, 'completed', NOW())
        ");
        
        return $stmt->execute([$transactionId, $amount, $refundId, $reason]);
    }
    
    /**
     * Process Stripe refund
     */
    private function processStripeRefund($transaction, $amount, $reason) {
        // Simplified Stripe refund process
        $refundData = [
            'charge' => $transaction['gateway_reference'],
            'amount' => round($amount * 100), // Convert to cents
            'reason' => 'requested_by_customer'
        ];
        
        // This would use the actual Stripe API
        return [
            'success' => true,
            'refund_id' => 're_' . uniqid(),
            'amount' => $amount
        ];
    }
    
    /**
     * Process PayPal refund
     */
    private function processPayPalRefund($transaction, $amount, $reason) {
        // Simplified PayPal refund process
        return [
            'success' => true,
            'refund_id' => 'paypal_refund_' . uniqid(),
            'amount' => $amount
        ];
    }
    
    /**
     * Release credit if it was reserved for the order
     */
    private function releaseCreditIfReserved($orderId, $customerId, $orderAmount) {
        try {
            // Check if credit was applied to this order by checking accounts_receivable
            $stmt = $this->pdo->prepare("SELECT credit_limit_applied FROM accounts_receivable WHERE id = (SELECT accounts_receivable_id FROM orders WHERE id = ?)");
            $stmt->execute([$orderId]);
            $receivable = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($receivable && $receivable['credit_limit_applied']) {
                // Release the reserved credit
                $this->creditCheck->releaseCreditForOrder($customerId, $orderAmount);
            }
        } catch (Exception $e) {
            // Log error but don't fail the payment
            error_log("Failed to release credit for order $orderId: " . $e->getMessage());
        }
    }
    
    /**
     * Create accounts receivable entry with credit information
     */
    private function createAccountsReceivableEntry($orderId, $order) {
        try {
            // Check if credit was applied
            $stmt = $this->pdo->prepare("SELECT id FROM customer_credit_limits WHERE customer_id = ?");
            $stmt->execute([$order['user_id']]);
            $creditLimit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $creditApplied = !empty($creditLimit);
            $creditApprovedAmount = $creditApplied ? $order['total'] : 0;
            
            // Create accounts receivable entry
            $this->creditCheck->createAccountsReceivableWithCredit(
                $orderId, 
                $order['user_id'], 
                $order['total'], 
                $creditApplied, 
                $creditApprovedAmount
            );
        } catch (Exception $e) {
            // Log error but don't fail the payment
            error_log("Failed to create accounts receivable entry for order $orderId: " . $e->getMessage());
        }
    }
    
    /**
     * Get payment methods configuration for frontend
     */
    public function getPaymentMethodsConfig() {
        return [
            'stripe' => [
                'enabled' => !empty($this->stripePublishableKey),
                'publishable_key' => $this->stripePublishableKey
            ],
            'paypal' => [
                'enabled' => !empty($this->paypalClientId),
                'client_id' => $this->paypalClientId,
                'mode' => $this->paypalMode
            ],
            'mock' => [
                'enabled' => true // Always enabled for testing
            ]
        ];
    }
    
    /**
     * Process Mock payment
     */
    private function processMockPayment($order, $paymentData) {
        $cardNumber = preg_replace('/\D/', '', $paymentData['card_number'] ?? '');
        $expiry = $paymentData['expiry'] ?? '';
        
        // Validate specific test card
        if ($cardNumber === '1231231231231233') {
            return [
                'success' => true,
                'gateway_reference' => 'mock_txn_' . uniqid(),
                'net_amount' => $order['total'], // In a real scenario, fees would be deducted
                'platform_fee' => 0,
                'gateway_fee' => 0,
                'raw_response' => json_encode(['status' => 'approved', 'mock' => true])
            ];
        } else {
            return [
                'success' => false,
                'error_message' => 'Invalid test card number. Use 1231231231231233'
            ];
        }
    }
}
?>