<?php
/**
 * Payment Gateway Webhook Handler
 * Handles webhooks from Stripe and PayPal for payment status updates
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/PaymentGateway.php';
require_once '../includes/NotificationSystem.php';

header('Content-Type: application/json');

// Get the webhook source from URL parameter
$gateway = $_GET['gateway'] ?? '';

if (!in_array($gateway, ['stripe', 'paypal'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid gateway']);
    exit;
}

try {
    $webhookHandler = new WebhookHandler($pdo);
    $result = $webhookHandler->processWebhook($gateway);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['received' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('webhook_error', [
        'gateway' => $gateway,
        'error' => $e->getMessage()
    ], 'error');
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

class WebhookHandler {
    private $pdo;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Process webhook based on gateway
     */
    public function processWebhook($gateway) {
        switch ($gateway) {
            case 'stripe':
                return $this->processStripeWebhook();
            case 'paypal':
                return $this->processPayPalWebhook();
            default:
                return ['success' => false, 'error' => 'Unsupported gateway'];
        }
    }
    
    /**
     * Process Stripe webhook
     */
    private function processStripeWebhook() {
        $payload = @file_get_contents('php://input');
        $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        // Get webhook secret from configuration
        $webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? 'whsec_test_...';
        
        // Verify webhook signature
        if (!$this->verifyStripeSignature($payload, $sigHeader, $webhookSecret)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Log webhook
        $this->logWebhook('stripe', $event['type'], $event['id'], $event);
        
        // Process event
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                return $this->handleStripePaymentSucceeded($event['data']['object']);
                
            case 'payment_intent.payment_failed':
                return $this->handleStripePaymentFailed($event['data']['object']);
                
            case 'charge.dispute.created':
                return $this->handleStripeDisputeCreated($event['data']['object']);
                
            case 'invoice.payment_succeeded':
                return $this->handleStripeInvoicePaymentSucceeded($event['data']['object']);
                
            default:
                // Log unhandled event
                Security::logSecurityEvent('webhook_unhandled', [
                    'gateway' => 'stripe',
                    'event_type' => $event['type']
                ], 'info');
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }
    
    /**
     * Process PayPal webhook — verifies signature before acting.
     */
    private function processPayPalWebhook() {
        $payload   = @file_get_contents('php://input');
        $headers   = getallheaders();
        $webhookId = env('PAYPAL_WEBHOOK_ID', '');

        if (empty($webhookId)) {
            error_log('PayPal webhook received but PAYPAL_WEBHOOK_ID not configured');
            return ['success' => true, 'message' => 'acknowledged'];
        }

        if (!$this->verifyPayPalSignature($payload, $headers, $webhookId)) {
            Security::logSecurityEvent('webhook_signature_invalid', ['gateway' => 'paypal'], 'warning');
            http_response_code(401);
            return ['success' => false, 'error' => 'Signature verification failed'];
        }

        $event = json_decode($payload, true);

        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }

        // Log webhook
        $this->logWebhook('paypal', $event['event_type'], $event['id'], $event);

        // Process event
        switch ($event['event_type']) {
            case 'CHECKOUT.ORDER.APPROVED':
                return $this->handlePayPalOrderApproved($event['resource']);
                
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handlePayPalPaymentCompleted($event['resource']);
                
            case 'PAYMENT.CAPTURE.DENIED':
                return $this->handlePayPalPaymentDenied($event['resource']);
                
            default:
                Security::logSecurityEvent('webhook_unhandled', [
                    'gateway' => 'paypal',
                    'event_type' => $event['event_type']
                ], 'info');
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }
    
    /**
     * Handle Stripe payment succeeded
     */
    private function handleStripePaymentSucceeded($paymentIntent) {
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;
        
        if (!$orderId) {
            return ['success' => false, 'error' => 'No order ID in metadata'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Update payment transaction
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'completed', updated_at = NOW()
                WHERE gateway_reference = ? AND payment_method = 'stripe'
            ");
            $stmt->execute([$paymentIntent['id']]);
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET status = 'paid', payment_status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Get order details for notifications
            $stmt = $this->pdo->prepare("
                SELECT o.*, u.email as customer_email
                FROM orders o
                JOIN users u ON o.customer_id = u.id
                WHERE o.id = ?
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();
            
            if ($order) {
                // Notify customer
                $this->notificationSystem->notifyPaymentConfirmed(
                    $order['customer_id'],
                    $orderId,
                    $order['total_amount']
                );
                
                // Notify merchants
                $stmt = $this->pdo->prepare("
                    SELECT DISTINCT p.merchant_id 
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.id
                    WHERE oi.order_id = ?
                ");
                $stmt->execute([$orderId]);
                $merchants = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                foreach ($merchants as $merchantId) {
                    $this->notificationSystem->notifyOrderReceived(
                        $merchantId,
                        $orderId,
                        $order['total_amount'],
                        $order['customer_email']
                    );
                }
            }
            
            $this->pdo->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Handle Stripe payment failed
     */
    private function handleStripePaymentFailed($paymentIntent) {
        $orderId = $paymentIntent['metadata']['order_id'] ?? null;
        
        if (!$orderId) {
            return ['success' => false, 'error' => 'No order ID in metadata'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Update payment transaction
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'failed', failure_reason = ?, updated_at = NOW()
                WHERE gateway_reference = ? AND payment_method = 'stripe'
            ");
            $stmt->execute([
                $paymentIntent['last_payment_error']['message'] ?? 'Payment failed',
                $paymentIntent['id']
            ]);
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET status = 'payment_failed', payment_status = 'failed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Notify customer of payment failure
            $stmt = $this->pdo->prepare("SELECT customer_id FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $customerId = $stmt->fetchColumn();
            
            if ($customerId) {
                $this->notificationSystem->notifyPaymentFailed($customerId, $orderId);
            }
            
            $this->pdo->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Handle Stripe dispute created
     */
    private function handleStripeDisputeCreated($dispute) {
        try {
            // Find the transaction
            $stmt = $this->pdo->prepare("
                SELECT id, order_id FROM payment_transactions 
                WHERE gateway_reference = ? AND payment_method = 'stripe'
            ");
            $stmt->execute([$dispute['charge']]);
            $transaction = $stmt->fetch();
            
            if (!$transaction) {
                return ['success' => false, 'error' => 'Transaction not found'];
            }
            
            // Record dispute
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_disputes (
                    transaction_id, dispute_type, gateway_dispute_id, amount, 
                    currency, reason, status, evidence_due_by
                ) VALUES (?, 'chargeback', ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $transaction['id'],
                $dispute['id'],
                $dispute['amount'] / 100, // Convert from cents
                $dispute['currency'],
                $dispute['reason'],
                $dispute['status'],
                isset($dispute['evidence_details']['due_by']) ? date('Y-m-d H:i:s', $dispute['evidence_details']['due_by']) : null
            ]);
            
            // Notify relevant parties about dispute
            // Implementation depends on business requirements
            
            return ['success' => true];
            
        } catch (Exception $e) {
            throw $e;
        }
    }
    
    /**
     * Handle PayPal payment completed
     */
    private function handlePayPalPaymentCompleted($capture) {
        // Extract order ID from custom_id or other field
        $orderId = $capture['custom_id'] ?? null;
        
        if (!$orderId) {
            return ['success' => false, 'error' => 'No order ID found'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Update payment transaction
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'completed', updated_at = NOW()
                WHERE gateway_reference = ? AND payment_method = 'paypal'
            ");
            $stmt->execute([$capture['id']]);
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET status = 'paid', payment_status = 'paid', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            $this->pdo->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Handle PayPal payment denied
     */
    private function handlePayPalPaymentDenied($capture) {
        $orderId = $capture['custom_id'] ?? null;
        
        if (!$orderId) {
            return ['success' => false, 'error' => 'No order ID found'];
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Update payment transaction
            $stmt = $this->pdo->prepare("
                UPDATE payment_transactions 
                SET status = 'failed', failure_reason = 'Payment denied by PayPal', updated_at = NOW()
                WHERE gateway_reference = ? AND payment_method = 'paypal'
            ");
            $stmt->execute([$capture['id']]);
            
            // Update order status
            $stmt = $this->pdo->prepare("
                UPDATE orders 
                SET status = 'payment_failed', payment_status = 'failed', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            $this->pdo->commit();
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Verify Stripe webhook signature
     */
    private function verifyStripeSignature($payload, $sigHeader, $secret) {
        $elements = explode(',', $sigHeader);
        $signature = null;
        $timestamp = null;
        
        foreach ($elements as $element) {
            if (strpos($element, 't=') === 0) {
                $timestamp = substr($element, 2);
            } elseif (strpos($element, 'v1=') === 0) {
                $signature = substr($element, 3);
            }
        }
        
        if (!$signature || !$timestamp) {
            return false;
        }
        
        // Check timestamp (should be within 5 minutes)
        if (abs(time() - $timestamp) > 300) {
            return false;
        }
        
        // Verify signature
        $expectedSignature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Verify PayPal webhook signature via PayPal's verify-webhook-signature API.
     * https://developer.paypal.com/docs/api/webhooks/v1/#verify-webhook-signature
     */
    private function verifyPayPalSignature(string $payload, array $headers, string $webhookId): bool {
        $clientId     = env('PAYPAL_CLIENT_ID', '');
        $clientSecret = env('PAYPAL_SECRET', '');

        if (empty($clientId) || empty($clientSecret)) {
            error_log('PayPal credentials not configured — cannot verify webhook signature');
            return false;
        }

        $live    = strtolower(env('PAYPAL_MODE', 'sandbox')) === 'live';
        $baseUrl = $live ? 'https://api.paypal.com' : 'https://api.sandbox.paypal.com';

        // Get access token
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $baseUrl . '/v1/oauth2/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $clientId . ':' . $clientSecret,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        ]);
        $tokenResponse = curl_exec($ch);
        curl_close($ch);

        $tokenData    = json_decode($tokenResponse, true);
        $accessToken  = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            error_log('PayPal webhook: failed to obtain access token for signature verification');
            return false;
        }

        // Normalise header names to uppercase-with-hyphens (getallheaders() is case-insensitive)
        $normalised = [];
        foreach ($headers as $k => $v) {
            $normalised[strtoupper($k)] = $v;
        }

        $verifyPayload = [
            'auth_algo'        => $normalised['PAYPAL-AUTH-ALGO']        ?? '',
            'cert_url'         => $normalised['PAYPAL-CERT-URL']         ?? '',
            'transmission_id'  => $normalised['PAYPAL-TRANSMISSION-ID']  ?? '',
            'transmission_sig' => $normalised['PAYPAL-TRANSMISSION-SIG'] ?? '',
            'transmission_time'=> $normalised['PAYPAL-TRANSMISSION-TIME'] ?? '',
            'webhook_id'       => $webhookId,
            'webhook_event'    => json_decode($payload, true),
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $baseUrl . '/v1/notifications/verify-webhook-signature',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($verifyPayload),
        ]);
        $verifyResponse = curl_exec($ch);
        curl_close($ch);

        $verifyData = json_decode($verifyResponse, true);
        return ($verifyData['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * Log webhook for auditing
     */
    private function logWebhook($gateway, $eventType, $eventId, $payload) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO payment_webhooks (gateway, event_type, event_id, payload, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$gateway, $eventType, $eventId, json_encode($payload)]);
        } catch (Exception $e) {
            // Log error but don't fail webhook processing
            error_log("Failed to log webhook: " . $e->getMessage());
        }
    }
}
?>