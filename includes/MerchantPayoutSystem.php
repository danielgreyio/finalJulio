<?php
/**
 * Merchant Payout Management System
 * Handles commission calculations, payout processing, and merchant earnings
 */

require_once 'security.php';

class MerchantPayoutSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate pending payouts for a merchant
     */
    public function calculatePendingPayouts($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as pending_orders,
                COALESCE(SUM(net_amount), 0) as total_pending,
                MIN(created_at) as oldest_pending
            FROM merchant_commissions 
            WHERE merchant_id = ? AND status = 'pending_payout'
        ");
        $stmt->execute([$merchantId]);
        
        return $stmt->fetch();
    }
    
    /**
     * Get merchant earnings summary
     */
    public function getMerchantEarnings($merchantId, $period = '30_days') {
        $dateCondition = match($period) {
            '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
            '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                COALESCE(SUM(gross_amount), 0) as gross_earnings,
                COALESCE(SUM(commission_amount), 0) as total_fees,
                COALESCE(SUM(net_amount), 0) as net_earnings,
                COALESCE(SUM(CASE WHEN status = 'paid_out' THEN net_amount ELSE 0 END), 0) as paid_out,
                COALESCE(SUM(CASE WHEN status = 'pending_payout' THEN net_amount ELSE 0 END), 0) as pending_payout,
                AVG(commission_rate) as avg_commission_rate
            FROM merchant_commissions 
            WHERE merchant_id = ? AND created_at >= {$dateCondition}
        ");
        $stmt->execute([$merchantId]);
        
        return $stmt->fetch();
    }
    
    /**
     * Get detailed commission history
     */
    public function getCommissionHistory($merchantId, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->pdo->prepare("
            SELECT 
                mc.*,
                o.id as order_number,
                o.created_at as order_date,
                u.email as customer_email
            FROM merchant_commissions mc
            JOIN orders o ON mc.order_id = o.id
            JOIN users u ON o.user_id = u.id
            WHERE mc.merchant_id = ?
            ORDER BY mc.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$merchantId, $limit, $offset]);
        $commissions = $stmt->fetchAll();
        
        // Get total count
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM merchant_commissions WHERE merchant_id = ?
        ");
        $stmt->execute([$merchantId]);
        $totalCount = $stmt->fetchColumn();
        
        return [
            'commissions' => $commissions,
            'total_count' => $totalCount,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalCount / $limit)
        ];
    }
    
    /**
     * Process payout for merchant
     */
    public function processPayout($merchantId, $payoutMethodId, $amount = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get merchant payout method
            $payoutMethod = $this->getMerchantPayoutMethod($payoutMethodId, $merchantId);
            
            if (!$payoutMethod || !$payoutMethod['is_verified']) {
                throw new Exception("Invalid or unverified payout method");
            }
            
            // Get pending commissions
            $pendingCommissions = $this->getPendingCommissions($merchantId, $amount);
            
            if (empty($pendingCommissions)) {
                throw new Exception("No pending commissions to payout");
            }
            
            $totalAmount = array_sum(array_column($pendingCommissions, 'net_amount'));
            
            // Check minimum payout amount
            $minPayout = $this->getMerchantMinimumPayout($merchantId);
            if ($totalAmount < $minPayout) {
                throw new Exception("Payout amount below minimum threshold of $" . number_format($minPayout, 2));
            }
            
            // Process payout based on method
            $payoutResult = $this->executePayout($payoutMethod, $totalAmount, $merchantId);
            
            if ($payoutResult['success']) {
                // Update commission records
                $commissionIds = array_column($pendingCommissions, 'id');
                $this->updateCommissionStatus($commissionIds, 'paid_out', $payoutResult['payout_reference']);
                
                // Record payout transaction
                $payoutId = $this->recordPayoutTransaction($merchantId, $payoutMethodId, $totalAmount, $payoutResult);
                
                $this->pdo->commit();
                
                return [
                    'success' => true,
                    'payout_id' => $payoutId,
                    'amount' => $totalAmount,
                    'commission_count' => count($pendingCommissions),
                    'payout_reference' => $payoutResult['payout_reference']
                ];
            } else {
                throw new Exception($payoutResult['error_message']);
            }
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            
            Security::logSecurityEvent('payout_failed', [
                'merchant_id' => $merchantId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ], 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute payout based on method type
     */
    private function executePayout($payoutMethod, $amount, $merchantId) {
        switch ($payoutMethod['method_type']) {
            case 'stripe_express':
                return $this->processStripeExpressPayout($payoutMethod, $amount, $merchantId);
                
            case 'paypal':
                return $this->processPayPalPayout($payoutMethod, $amount, $merchantId);
                
            case 'bank_account':
                return $this->processBankTransfer($payoutMethod, $amount, $merchantId);
                
            default:
                return ['success' => false, 'error_message' => 'Unsupported payout method'];
        }
    }
    
    /**
     * Process Stripe Express payout
     */
    private function processStripeExpressPayout($payoutMethod, $amount, $merchantId) {
        try {
            $accountDetails = json_decode($payoutMethod['account_details'], true);
            
            // Create Stripe transfer (simplified - use actual Stripe API)
            $transferData = [
                'amount' => round($amount * 100), // Convert to cents
                'currency' => 'usd',
                'destination' => $accountDetails['stripe_account_id'],
                'description' => "Payout for merchant ID: {$merchantId}",
                'metadata' => [
                    'merchant_id' => $merchantId,
                    'payout_method_id' => $payoutMethod['id']
                ]
            ];
            
            // This would use actual Stripe API
            $transfer = $this->createStripeTransfer($transferData);
            
            if ($transfer && $transfer['id']) {
                return [
                    'success' => true,
                    'payout_reference' => $transfer['id'],
                    'estimated_arrival' => date('Y-m-d', strtotime('+2 business days')),
                    'raw_response' => json_encode($transfer)
                ];
            } else {
                return ['success' => false, 'error_message' => 'Stripe transfer failed'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error_message' => $e->getMessage()];
        }
    }
    
    /**
     * Process PayPal payout
     */
    private function processPayPalPayout($payoutMethod, $amount, $merchantId) {
        try {
            $accountDetails = json_decode($payoutMethod['account_details'], true);
            
            // Create PayPal payout batch
            $payoutData = [
                'sender_batch_header' => [
                    'sender_batch_id' => 'payout_' . $merchantId . '_' . time(),
                    'email_subject' => 'You have received a payment from VentDepot',
                    'email_message' => 'Thank you for selling on VentDepot!'
                ],
                'items' => [
                    [
                        'recipient_type' => 'EMAIL',
                        'amount' => [
                            'value' => number_format($amount, 2, '.', ''),
                            'currency' => 'USD'
                        ],
                        'receiver' => $accountDetails['paypal_email'],
                        'note' => "Merchant payout for VentDepot sales",
                        'sender_item_id' => "merchant_{$merchantId}_" . time()
                    ]
                ]
            ];
            
            // This would use actual PayPal API
            $payoutBatch = $this->createPayPalPayout($payoutData);
            
            if ($payoutBatch && $payoutBatch['batch_header']['batch_status'] === 'PENDING') {
                return [
                    'success' => true,
                    'payout_reference' => $payoutBatch['batch_header']['payout_batch_id'],
                    'estimated_arrival' => date('Y-m-d', strtotime('+1 business day')),
                    'raw_response' => json_encode($payoutBatch)
                ];
            } else {
                return ['success' => false, 'error_message' => 'PayPal payout failed'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error_message' => $e->getMessage()];
        }
    }
    
    /**
     * Process bank transfer (manual processing)
     */
    private function processBankTransfer($payoutMethod, $amount, $merchantId) {
        // Bank transfers would typically be processed manually or through banking APIs
        return [
            'success' => true,
            'payout_reference' => 'BANK_' . uniqid(),
            'estimated_arrival' => date('Y-m-d', strtotime('+3 business days')),
            'note' => 'Bank transfer will be processed manually within 1-2 business days'
        ];
    }
    
    /**
     * Get merchant payout method
     */
    private function getMerchantPayoutMethod($payoutMethodId, $merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM merchant_payout_methods 
            WHERE id = ? AND merchant_id = ?
        ");
        $stmt->execute([$payoutMethodId, $merchantId]);
        return $stmt->fetch();
    }
    
    /**
     * Get pending commissions
     */
    private function getPendingCommissions($merchantId, $maxAmount = null) {
        $sql = "
            SELECT * FROM merchant_commissions 
            WHERE merchant_id = ? AND status = 'pending_payout'
            ORDER BY created_at ASC
        ";
        
        if ($maxAmount) {
            $sql .= " AND net_amount <= ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$merchantId, $maxAmount]);
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$merchantId]);
        }
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get merchant minimum payout amount
     */
    private function getMerchantMinimumPayout($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT minimum_payout FROM user_profiles WHERE user_id = ?
        ");
        $stmt->execute([$merchantId]);
        $result = $stmt->fetchColumn();
        
        return $result ?: 25.00; // Default minimum
    }
    
    /**
     * Update commission status
     */
    private function updateCommissionStatus($commissionIds, $status, $payoutReference = null) {
        $placeholders = str_repeat('?,', count($commissionIds) - 1) . '?';
        
        $stmt = $this->pdo->prepare("
            UPDATE merchant_commissions 
            SET status = ?, payout_reference = ?, payout_date = NOW(), updated_at = NOW()
            WHERE id IN ({$placeholders})
        ");
        
        $params = [$status, $payoutReference];
        $params = array_merge($params, $commissionIds);
        
        return $stmt->execute($params);
    }
    
    /**
     * Record payout transaction
     */
    private function recordPayoutTransaction($merchantId, $payoutMethodId, $amount, $payoutResult) {
        $stmt = $this->pdo->prepare("
            INSERT INTO merchant_payouts (
                merchant_id, payout_method_id, amount, status, 
                gateway_reference, estimated_arrival, created_at
            ) VALUES (?, ?, ?, 'processing', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $merchantId,
            $payoutMethodId,
            $amount,
            $payoutResult['payout_reference'],
            $payoutResult['estimated_arrival'] ?? null
        ]);
        
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Add merchant payout method
     */
    public function addPayoutMethod($merchantId, $methodType, $accountDetails) {
        try {
            // Validate account details based on method type
            $validatedDetails = $this->validatePayoutDetails($methodType, $accountDetails);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO merchant_payout_methods (
                    merchant_id, method_type, account_details, verification_status
                ) VALUES (?, ?, ?, 'pending')
            ");
            
            $result = $stmt->execute([
                $merchantId,
                $methodType,
                json_encode($validatedDetails)
            ]);
            
            if ($result) {
                $payoutMethodId = $this->pdo->lastInsertId();
                
                // Trigger verification process
                $this->initiateVerification($payoutMethodId, $methodType, $validatedDetails);
                
                return [
                    'success' => true,
                    'payout_method_id' => $payoutMethodId,
                    'message' => 'Payout method added. Verification required.'
                ];
            }
            
            return ['success' => false, 'error' => 'Failed to add payout method'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Validate payout details
     */
    private function validatePayoutDetails($methodType, $details) {
        switch ($methodType) {
            case 'bank_account':
                if (empty($details['account_number']) || empty($details['routing_number'])) {
                    throw new Exception("Bank account and routing number required");
                }
                return [
                    'account_number' => preg_replace('/\D/', '', $details['account_number']),
                    'routing_number' => preg_replace('/\D/', '', $details['routing_number']),
                    'account_holder_name' => $details['account_holder_name'],
                    'bank_name' => $details['bank_name']
                ];
                
            case 'paypal':
                if (empty($details['paypal_email']) || !filter_var($details['paypal_email'], FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Valid PayPal email required");
                }
                return [
                    'paypal_email' => strtolower(trim($details['paypal_email']))
                ];
                
            case 'stripe_express':
                if (empty($details['stripe_account_id'])) {
                    throw new Exception("Stripe account ID required");
                }
                return [
                    'stripe_account_id' => $details['stripe_account_id']
                ];
                
            default:
                throw new Exception("Unsupported payout method type");
        }
    }
    
    /**
     * Initiate verification process
     */
    private function initiateVerification($payoutMethodId, $methodType, $details) {
        // Implementation would depend on the method type
        // For example, sending micro deposits for bank accounts
        // or verifying PayPal account ownership
        
        Security::logSecurityEvent('payout_method_verification_initiated', [
            'payout_method_id' => $payoutMethodId,
            'method_type' => $methodType
        ], 'info');
    }
    
    /**
     * Get payout analytics for admin
     */
    public function getPayoutAnalytics($period = '30_days') {
        $dateCondition = match($period) {
            '7_days' => 'DATE_SUB(NOW(), INTERVAL 7 DAY)',
            '30_days' => 'DATE_SUB(NOW(), INTERVAL 30 DAY)',
            '90_days' => 'DATE_SUB(NOW(), INTERVAL 90 DAY)',
            '1_year' => 'DATE_SUB(NOW(), INTERVAL 1 YEAR)',
            default => 'DATE_SUB(NOW(), INTERVAL 30 DAY)'
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT mc.merchant_id) as active_merchants,
                COUNT(*) as total_commissions,
                COALESCE(SUM(mc.gross_amount), 0) as total_gross,
                COALESCE(SUM(mc.commission_amount), 0) as platform_revenue,
                COALESCE(SUM(mc.net_amount), 0) as merchant_earnings,
                COALESCE(SUM(CASE WHEN mc.status = 'paid_out' THEN mc.net_amount ELSE 0 END), 0) as paid_out,
                COALESCE(SUM(CASE WHEN mc.status = 'pending_payout' THEN mc.net_amount ELSE 0 END), 0) as pending_payouts,
                AVG(mc.commission_rate) as avg_commission_rate
            FROM merchant_commissions mc
            WHERE mc.created_at >= {$dateCondition}
        ");
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Simplified Stripe transfer creation (placeholder)
     */
    private function createStripeTransfer($data) {
        // This would use the actual Stripe API
        return [
            'id' => 'tr_' . uniqid(),
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'destination' => $data['destination']
        ];
    }
    
    /**
     * Simplified PayPal payout creation (placeholder)
     */
    private function createPayPalPayout($data) {
        // This would use the actual PayPal API
        return [
            'batch_header' => [
                'payout_batch_id' => 'batch_' . uniqid(),
                'batch_status' => 'PENDING'
            ]
        ];
    }
}

/**
 * Create merchant payouts table if it doesn't exist
 */
function createMerchantPayoutsTable($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS merchant_payouts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            merchant_id INT NOT NULL,
            payout_method_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status ENUM('processing', 'completed', 'failed', 'cancelled') DEFAULT 'processing',
            gateway_reference VARCHAR(255) NULL,
            estimated_arrival DATE NULL,
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (merchant_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (payout_method_id) REFERENCES merchant_payout_methods(id) ON DELETE CASCADE,
            INDEX idx_merchant_payouts_merchant (merchant_id),
            INDEX idx_merchant_payouts_status (status)
        )
    ";
    $pdo->exec($sql);
}
?>