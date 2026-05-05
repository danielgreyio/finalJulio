<?php
/**
 * Payment Gateway — orchestration layer.
 *
 * Handles DB transaction, order verification, commission splits, and credit release.
 * Delegates actual API calls to the active PaymentProvider (Stripe / PayPal / MercadoPago),
 * selected via .env PAYMENT_PROVIDER. To swap processors, change one line in .env.
 */

require_once 'security.php';
require_once __DIR__ . '/CreditCheck.php';
require_once __DIR__ . '/payments/PaymentService.php';

class PaymentGateway {
    private $pdo;
    private $creditCheck;

    public function __construct($pdo) {
        $this->pdo         = $pdo;
        $this->creditCheck = new CreditCheck($pdo);
    }

    /**
     * Process payment through the active provider.
     */
    public function processPayment($orderId, $paymentMethod, $paymentData) {
        try {
            $this->pdo->beginTransaction();

            $order = $this->getOrderDetails($orderId);
            if (!$order) {
                throw new Exception("Order not found");
            }

            if ($order['status'] !== 'pending_payment') {
                throw new Exception("Order is not in a payable state");
            }

            $provider = PaymentService::getProvider();
            $result   = $provider->charge($order, $paymentData);

            // Use the configured provider name for the transaction record (authoritative)
            $activeMethod = strtolower(env('PAYMENT_PROVIDER', $paymentMethod));

            if ($result['success']) {
                // Record payment transaction
                $transactionId = $this->recordTransaction($orderId, $activeMethod, $result);
                
                // Update order status
                $this->updateOrderStatus($orderId, 'paid', $transactionId);
                
                // Process commission splits
                $this->processCommissionSplits($orderId, $result['net_amount']);
                
                // Release credit if it was reserved
                $this->releaseCreditIfReserved($orderId, $order['customer_id'], $order['total_amount']);
                
                // Create accounts receivable entry with credit information
                $this->createAccountsReceivableEntry($orderId, $order);
                
                $this->pdo->commit();
                
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
        
        $grossAmount = $order['total_amount'];
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
            JOIN users u ON o.customer_id = u.id
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
     * Process refund via the provider that originally processed the transaction.
     */
    public function processRefund($transactionId, $amount = null, $reason = '') {
        try {
            $transaction = $this->getTransaction($transactionId);
            if (!$transaction) {
                throw new Exception("Transaction not found");
            }

            $refundAmount = $amount ?? $transaction['amount'];
            $gatewayRef   = $transaction['gateway_reference'];
            $method       = $transaction['payment_method'];

            // Route to the correct provider regardless of current PAYMENT_PROVIDER setting
            $provider = match ($method) {
                'stripe'      => new StripeProvider(),
                'paypal'      => new PayPalProvider(),
                'mercadopago' => new MercadoPagoProvider(),
                default       => throw new Exception("Unknown payment method: $method"),
            };

            $result = $provider->refund($gatewayRef, $refundAmount, $reason);

            if ($result['success']) {
                $this->recordRefund($transactionId, $refundAmount, $result['refund_id'], $reason);
                return ['success' => true, 'refund_id' => $result['refund_id'], 'amount' => $refundAmount];
            }

            throw new Exception($result['error'] ?? 'Refund failed');

        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
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
            $stmt->execute([$order['customer_id']]);
            $creditLimit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $creditApplied = !empty($creditLimit);
            $creditApprovedAmount = $creditApplied ? $order['total_amount'] : 0;
            
            // Create accounts receivable entry
            $this->creditCheck->createAccountsReceivableWithCredit(
                $orderId, 
                $order['customer_id'], 
                $order['total_amount'], 
                $creditApplied, 
                $creditApprovedAmount
            );
        } catch (Exception $e) {
            // Log error but don't fail the payment
            error_log("Failed to create accounts receivable entry for order $orderId: " . $e->getMessage());
        }
    }
    
    /**
     * Get payment methods configuration for frontend.
     * Delegates to PaymentService so checkout.php doesn't need to change.
     */
    public function getPaymentMethodsConfig(): array {
        return PaymentService::getAllFrontendConfigs();
    }
}
?>