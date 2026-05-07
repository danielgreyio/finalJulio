<?php
/**
 * General Webhook Handler
 * Handles webhooks from various external systems
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/NotificationSystem.php';

header('Content-Type: application/json');

// Get the webhook source from URL parameter
$source = $_GET['source'] ?? '';

if (empty($source)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid source']);
    exit;
}

try {
    $webhookHandler = new GeneralWebhookHandler($pdo);
    $result = $webhookHandler->processWebhook($source);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode(['received' => true]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    
} catch (Exception $e) {
    Security::logSecurityEvent('webhook_error', [
        'source' => $source,
        'error' => $e->getMessage()
    ], 'error');
    
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

class GeneralWebhookHandler {
    private $pdo;
    private $notificationSystem;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->notificationSystem = new NotificationSystem($pdo);
    }
    
    /**
     * Process webhook based on source
     */
    public function processWebhook($source) {
        // Log the webhook
        $this->logWebhook($source);
        
        switch ($source) {
            case 'shopify':
                return $this->processShopifyWebhook();
            case 'woocommerce':
                return $this->processWooCommerceWebhook();
            case 'amazon':
                return $this->processAmazonWebhook();
            case 'facebook':
                return $this->processFacebookWebhook();
            case 'google':
                return $this->processGoogleWebhook();
            case 'mailchimp':
                return $this->processMailchimpWebhook();
            case 'shipstation':
                return $this->processShipStationWebhook();
            default:
                // Try to process as a generic webhook
                return $this->processGenericWebhook($source);
        }
    }
    
    /**
     * Process Shopify webhook
     */
    private function processShopifyWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        // Verify Shopify webhook signature
        $hmac = $headers['X-Shopify-Hmac-Sha256'] ?? '';
        $topic = $headers['X-Shopify-Topic'] ?? '';
        $shopDomain = $headers['X-Shopify-Shop-Domain'] ?? '';
        
        // Get Shopify webhook secret from configuration
        $webhookSecret = $_ENV['SHOPIFY_WEBHOOK_SECRET'] ?? '';
        
        // Verify webhook signature
        if (!$this->verifyShopifySignature($payload, $hmac, $webhookSecret)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Process event based on topic
        switch ($topic) {
            case 'orders/create':
                return $this->handleShopifyOrderCreate($event);
            case 'orders/updated':
                return $this->handleShopifyOrderUpdate($event);
            case 'products/create':
                return $this->handleShopifyProductCreate($event);
            case 'products/update':
                return $this->handleShopifyProductUpdate($event);
            case 'inventory_levels/update':
                return $this->handleShopifyInventoryUpdate($event);
            default:
                // Log unhandled event
                Security::logSecurityEvent('webhook_unhandled', [
                    'source' => 'shopify',
                    'event_type' => $topic
                ], 'info');
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }
    
    /**
     * Process WooCommerce webhook
     */
    private function processWooCommerceWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        // Verify WooCommerce webhook signature
        $signature = $headers['X-WC-Webhook-Signature'] ?? '';
        $topic = $headers['X-WC-Webhook-Topic'] ?? '';
        $resource = $headers['X-WC-Webhook-Resource'] ?? '';
        
        // Get WooCommerce webhook secret from configuration
        $webhookSecret = $_ENV['WOOCOMMERCE_WEBHOOK_SECRET'] ?? '';
        
        // Verify webhook signature
        if (!$this->verifyWooCommerceSignature($payload, $signature, $webhookSecret)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Process event based on topic
        switch ($topic) {
            case 'order.created':
                return $this->handleWooCommerceOrderCreate($event);
            case 'order.updated':
                return $this->handleWooCommerceOrderUpdate($event);
            case 'product.created':
                return $this->handleWooCommerceProductCreate($event);
            case 'product.updated':
                return $this->handleWooCommerceProductUpdate($event);
            default:
                // Log unhandled event
                Security::logSecurityEvent('webhook_unhandled', [
                    'source' => 'woocommerce',
                    'event_type' => $topic
                ], 'info');
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }
    
    /**
     * Process Amazon webhook
     */
    private function processAmazonWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Process Amazon events
        // This would depend on the specific Amazon service (SNS, SQS, etc.)
        // For now, we'll just log and return success
        Security::logSecurityEvent('webhook_received', [
            'source' => 'amazon',
            'event' => $event
        ], 'info');
        
        return ['success' => true, 'message' => 'Amazon webhook received'];
    }
    
    /**
     * Process Facebook webhook
     */
    private function processFacebookWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        // Facebook uses a challenge-response mechanism for verification
        if (isset($_GET['hub_challenge'])) {
            $verifyToken = $_ENV['FACEBOOK_VERIFY_TOKEN'] ?? '';
            
            if ($_GET['hub_verify_token'] === $verifyToken) {
                echo intval($_GET['hub_challenge']);
                exit;
            } else {
                http_response_code(403);
                exit;
            }
        }
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Process Facebook events (ads, page updates, etc.)
        // For now, we'll just log and return success
        Security::logSecurityEvent('webhook_received', [
            'source' => 'facebook',
            'event' => $event
        ], 'info');
        
        return ['success' => true, 'message' => 'Facebook webhook received'];
    }
    
    /**
     * Process Google webhook
     */
    private function processGoogleWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Process Google events (Analytics, Ads, etc.)
        // For now, we'll just log and return success
        Security::logSecurityEvent('webhook_received', [
            'source' => 'google',
            'event' => $event
        ], 'info');
        
        return ['success' => true, 'message' => 'Google webhook received'];
    }
    
    /**
     * Process Mailchimp webhook
     */
    private function processMailchimpWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        // Mailchimp sends data as form-encoded
        parse_str($payload, $event);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid data'];
        }
        
        // Process Mailchimp events (subscribes, unsubscribes, etc.)
        $type = $event['type'] ?? '';
        
        switch ($type) {
            case 'subscribe':
                return $this->handleMailchimpSubscribe($event);
            case 'unsubscribe':
                return $this->handleMailchimpUnsubscribe($event);
            case 'campaign':
                return $this->handleMailchimpCampaign($event);
            default:
                // Log unhandled event
                Security::logSecurityEvent('webhook_unhandled', [
                    'source' => 'mailchimp',
                    'event_type' => $type
                ], 'info');
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }
    
    /**
     * Process ShipStation webhook
     */
    private function processShipStationWebhook() {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Process ShipStation events (shipments, orders, etc.)
        $resourceUrl = $event['ResourceUrl'] ?? '';
        $eventType = $event['EventType'] ?? '';
        
        switch ($eventType) {
            case 'SHIP_NOTIFY':
                return $this->handleShipStationShipment($event);
            default:
                // Log unhandled event
                Security::logSecurityEvent('webhook_unhandled', [
                    'source' => 'shipstation',
                    'event_type' => $eventType
                ], 'info');
                return ['success' => true, 'message' => 'Event not handled'];
        }
    }
    
    /**
     * Process generic webhook
     */
    private function processGenericWebhook($source) {
        $payload = @file_get_contents('php://input');
        $headers = getallheaders();
        
        $event = json_decode($payload, true);
        
        if (!$event) {
            return ['success' => false, 'error' => 'Invalid JSON'];
        }
        
        // Log the generic webhook
        Security::logSecurityEvent('webhook_received', [
            'source' => $source,
            'event' => $event
        ], 'info');
        
        return ['success' => true, 'message' => 'Generic webhook received'];
    }
    
    /**
     * Handle Shopify order creation
     */
    private function handleShopifyOrderCreate($order) {
        try {
            // Extract relevant order information
            $orderId = $order['id'] ?? null;
            $orderNumber = $order['order_number'] ?? null;
            $totalPrice = $order['total_price'] ?? 0;
            $customer = $order['customer'] ?? [];
            $customerName = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
            $customerEmail = $customer['email'] ?? '';
            
            // In a real implementation, you would:
            // 1. Check if order already exists
            // 2. Create order in your system
            // 3. Update inventory
            // 4. Send notifications
            
            // For now, just log the event
            Security::logSecurityEvent('shopify_order_created', [
                'shopify_order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_price' => $totalPrice,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Shopify order update
     */
    private function handleShopifyOrderUpdate($order) {
        try {
            // Extract relevant order information
            $orderId = $order['id'] ?? null;
            $financialStatus = $order['financial_status'] ?? '';
            $fulfillmentStatus = $order['fulfillment_status'] ?? '';
            
            // In a real implementation, you would:
            // 1. Find existing order
            // 2. Update order status
            // 3. Send notifications based on status changes
            
            // For now, just log the event
            Security::logSecurityEvent('shopify_order_updated', [
                'shopify_order_id' => $orderId,
                'financial_status' => $financialStatus,
                'fulfillment_status' => $fulfillmentStatus
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Shopify product creation
     */
    private function handleShopifyProductCreate($product) {
        try {
            // Extract relevant product information
            $productId = $product['id'] ?? null;
            $title = $product['title'] ?? '';
            $variants = $product['variants'] ?? [];
            
            // In a real implementation, you would:
            // 1. Create product in your system
            // 2. Sync inventory
            // 3. Set up pricing
            
            // For now, just log the event
            Security::logSecurityEvent('shopify_product_created', [
                'shopify_product_id' => $productId,
                'title' => $title,
                'variant_count' => count($variants)
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Shopify product update
     */
    private function handleShopifyProductUpdate($product) {
        try {
            // Extract relevant product information
            $productId = $product['id'] ?? null;
            $title = $product['title'] ?? '';
            
            // In a real implementation, you would:
            // 1. Find existing product
            // 2. Update product details
            // 3. Sync inventory changes
            
            // For now, just log the event
            Security::logSecurityEvent('shopify_product_updated', [
                'shopify_product_id' => $productId,
                'title' => $title
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Shopify inventory update
     */
    private function handleShopifyInventoryUpdate($inventory) {
        try {
            // Extract relevant inventory information
            $inventoryItemId = $inventory['inventory_item_id'] ?? null;
            $available = $inventory['available'] ?? 0;
            $locationId = $inventory['location_id'] ?? null;
            
            // In a real implementation, you would:
            // 1. Find product variant by inventory_item_id
            // 2. Update inventory levels
            // 3. Trigger low stock alerts if needed
            
            // For now, just log the event
            Security::logSecurityEvent('shopify_inventory_updated', [
                'inventory_item_id' => $inventoryItemId,
                'available' => $available,
                'location_id' => $locationId
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle WooCommerce order creation
     */
    private function handleWooCommerceOrderCreate($order) {
        try {
            // Extract relevant order information
            $orderId = $order['id'] ?? null;
            $orderNumber = $order['number'] ?? null;
            $total = $order['total'] ?? 0;
            $customer = $order['billing'] ?? [];
            $customerName = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
            $customerEmail = $customer['email'] ?? '';
            
            // In a real implementation, you would:
            // 1. Check if order already exists
            // 2. Create order in your system
            // 3. Update inventory
            // 4. Send notifications
            
            // For now, just log the event
            Security::logSecurityEvent('woocommerce_order_created', [
                'woocommerce_order_id' => $orderId,
                'order_number' => $orderNumber,
                'total' => $total,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle WooCommerce order update
     */
    private function handleWooCommerceOrderUpdate($order) {
        try {
            // Extract relevant order information
            $orderId = $order['id'] ?? null;
            $status = $order['status'] ?? '';
            
            // In a real implementation, you would:
            // 1. Find existing order
            // 2. Update order status
            // 3. Send notifications based on status changes
            
            // For now, just log the event
            Security::logSecurityEvent('woocommerce_order_updated', [
                'woocommerce_order_id' => $orderId,
                'status' => $status
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle WooCommerce product creation
     */
    private function handleWooCommerceProductCreate($product) {
        try {
            // Extract relevant product information
            $productId = $product['id'] ?? null;
            $name = $product['name'] ?? '';
            $sku = $product['sku'] ?? '';
            
            // In a real implementation, you would:
            // 1. Create product in your system
            // 2. Sync inventory
            // 3. Set up pricing
            
            // For now, just log the event
            Security::logSecurityEvent('woocommerce_product_created', [
                'woocommerce_product_id' => $productId,
                'name' => $name,
                'sku' => $sku
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle WooCommerce product update
     */
    private function handleWooCommerceProductUpdate($product) {
        try {
            // Extract relevant product information
            $productId = $product['id'] ?? null;
            $name = $product['name'] ?? '';
            $sku = $product['sku'] ?? '';
            
            // In a real implementation, you would:
            // 1. Find existing product
            // 2. Update product details
            // 3. Sync inventory changes
            
            // For now, just log the event
            Security::logSecurityEvent('woocommerce_product_updated', [
                'woocommerce_product_id' => $productId,
                'name' => $name,
                'sku' => $sku
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Mailchimp subscribe
     */
    private function handleMailchimpSubscribe($event) {
        try {
            $email = $event['data']['email'] ?? '';
            $firstName = $event['data']['merges']['FNAME'] ?? '';
            $lastName = $event['data']['merges']['LNAME'] ?? '';
            
            // In a real implementation, you would:
            // 1. Add subscriber to your system
            // 2. Send welcome email
            // 3. Update marketing lists
            
            // For now, just log the event
            Security::logSecurityEvent('mailchimp_subscribe', [
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Mailchimp unsubscribe
     */
    private function handleMailchimpUnsubscribe($event) {
        try {
            $email = $event['data']['email'] ?? '';
            
            // In a real implementation, you would:
            // 1. Remove subscriber from your system
            // 2. Update marketing lists
            // 3. Handle data retention requirements
            
            // For now, just log the event
            Security::logSecurityEvent('mailchimp_unsubscribe', [
                'email' => $email
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle Mailchimp campaign
     */
    private function handleMailchimpCampaign($event) {
        try {
            $campaignId = $event['data']['id'] ?? '';
            $status = $event['data']['status'] ?? '';
            $subject = $event['data']['subject'] ?? '';
            
            // In a real implementation, you would:
            // 1. Track campaign performance
            // 2. Update marketing analytics
            // 3. Send notifications for important events
            
            // For now, just log the event
            Security::logSecurityEvent('mailchimp_campaign', [
                'campaign_id' => $campaignId,
                'status' => $status,
                'subject' => $subject
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle ShipStation shipment
     */
    private function handleShipStationShipment($event) {
        try {
            $shipmentId = $event['Shipment']['ShipmentID'] ?? '';
            $orderId = $event['Shipment']['OrderID'] ?? '';
            $trackingNumber = $event['Shipment']['TrackingNumber'] ?? '';
            $carrier = $event['Shipment']['Carrier'] ?? '';
            
            // In a real implementation, you would:
            // 1. Find the order
            // 2. Update order status to shipped
            // 3. Send shipping notification to customer
            // 4. Update tracking information
            
            // For now, just log the event
            Security::logSecurityEvent('shipstation_shipment', [
                'shipment_id' => $shipmentId,
                'order_id' => $orderId,
                'tracking_number' => $trackingNumber,
                'carrier' => $carrier
            ], 'info');
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Verify Shopify webhook signature
     */
    private function verifyShopifySignature($payload, $hmac, $secret) {
        $calculatedHmac = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($calculatedHmac, $hmac);
    }
    
    /**
     * Verify WooCommerce webhook signature
     */
    private function verifyWooCommerceSignature($payload, $signature, $secret) {
        $calculatedSignature = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        return hash_equals($calculatedSignature, $signature);
    }
    
    /**
     * Log webhook for auditing
     */
    private function logWebhook($source) {
        try {
            $payload = @file_get_contents('php://input');
            $headers = json_encode(getallheaders());
            
            $stmt = $this->pdo->prepare("
                INSERT INTO webhooks_log (source, headers, payload, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$source, $headers, $payload]);
        } catch (Exception $e) {
            // Log error but don't fail webhook processing
            error_log("Failed to log webhook: " . $e->getMessage());
        }
    }
}
?>