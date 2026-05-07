<?php
/**
 * VentDepot REST API Foundation
 * Comprehensive API system for mobile apps and third-party integrations
 */

require_once '../config/database.php';
require_once '../includes/security.php';

class VentDepotAPI {
    private $pdo;
    private $method;
    private $endpoint;
    private $verb;
    private $args;
    private $userId;
    private $userRole;
    
    public function __construct($pdo, $request) {
        $this->pdo = $pdo;
        
        header("Content-Type: application/json");
        $allowedOrigin = rtrim(env('APP_URL', ''), '/');
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        header("Access-Control-Allow-Origin: " . ($origin === $allowedOrigin ? $origin : $allowedOrigin));
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-CSRF-Token");
        header("Access-Control-Allow-Credentials: false");
        
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
        
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->args = explode('/', rtrim($request, '/'));
        $this->endpoint = array_shift($this->args);
        
        if (array_key_exists(0, $this->args) && !is_numeric($this->args[0])) {
            $this->verb = array_shift($this->args);
        }
        
        // Authenticate API request
        $this->authenticateRequest();
    }
    
    /**
     * Process API request
     */
    public function processAPI() {
        try {
            // Rate limiting
            if (!$this->checkRateLimit()) {
                return $this->response(['error' => 'Rate limit exceeded'], 429);
            }
            
            // Route to appropriate handler
            switch ($this->endpoint) {
                case 'auth':
                    return $this->handleAuth();
                case 'products':
                    return $this->handleProducts();
                case 'orders':
                    return $this->handleOrders();
                case 'users':
                    return $this->handleUsers();
                case 'merchants':
                    return $this->handleMerchants();
                case 'reviews':
                    return $this->handleReviews();
                case 'messages':
                    return $this->handleMessages();
                case 'payments':
                    return $this->handlePayments();
                case 'analytics':
                    return $this->handleAnalytics();
                case 'search':
                    return $this->handleSearch();
                default:
                    return $this->response(['error' => 'Invalid endpoint'], 404);
            }
        } catch (Exception $e) {
            Security::logSecurityEvent('api_error', [
                'endpoint' => $this->endpoint,
                'method' => $this->method,
                'error' => $e->getMessage(),
                'user_id' => $this->userId
            ], 'error');
            
            return $this->response(['error' => 'Internal server error'], 500);
        }
    }
    
    /**
     * Authenticate API request
     */
    private function authenticateRequest() {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? '';
        $authHeader = $headers['Authorization'] ?? '';
        
        // Public endpoints that don't require authentication
        $publicEndpoints = ['auth', 'products', 'search'];
        $publicMethods = ['GET'];
        
        if (in_array($this->endpoint, $publicEndpoints) && 
            ($this->method === 'GET' || $this->endpoint === 'auth')) {
            return; // Allow public access
        }
        
        // API Key authentication
        if ($apiKey) {
            $user = $this->validateAPIKey($apiKey);
            if ($user) {
                $this->userId = $user['id'];
                $this->userRole = $user['role'];
                return;
            }
        }
        
        // JWT Bearer token authentication
        if (strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            $user = $this->validateJWTToken($token);
            if ($user) {
                $this->userId = $user['id'];
                $this->userRole = $user['role'];
                return;
            }
        }
        
        // Authentication required but not provided
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit;
    }
    
    /**
     * Validate API key
     */
    private function validateAPIKey($apiKey) {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.role, u.email, ak.last_used_at
            FROM api_keys ak
            JOIN users u ON ak.user_id = u.id
            WHERE ak.api_key = ? AND ak.is_active = TRUE AND ak.expires_at > NOW()
        ");
        $stmt->execute([$apiKey]);
        $result = $stmt->fetch();
        
        if ($result) {
            // Update last used timestamp
            $stmt = $this->pdo->prepare("
                UPDATE api_keys SET last_used_at = NOW(), usage_count = usage_count + 1 
                WHERE api_key = ?
            ");
            $stmt->execute([$apiKey]);
        }
        
        return $result;
    }
    
    /**
     * Validate JWT token
     */
    private function validateJWTToken($token) {
        try {
            // Simple JWT validation (in production, use a proper JWT library)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $header = json_decode(base64_decode($parts[0]), true);
            $payload = json_decode(base64_decode($parts[1]), true);
            $signature = $parts[2];
            
            // Verify signature
            $expectedSignature = base64_encode(hash_hmac(
                'sha256',
                $parts[0] . '.' . $parts[1],
                $_ENV['JWT_SECRET'] ?? (static function() { throw new \RuntimeException('JWT_SECRET not set in .env'); })(),
                true
            ));
            
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            // Get user data
            if (isset($payload['user_id'])) {
                $stmt = $this->pdo->prepare("
                    SELECT id, role, email FROM users WHERE id = ? AND status = 'active'
                ");
                $stmt->execute([$payload['user_id']]);
                return $stmt->fetch();
            }
            
        } catch (Exception $e) {
            return false;
        }
        
        return false;
    }
    
    /**
     * Check rate limiting
     */
    private function checkRateLimit() {
        $ip = $_SERVER['REMOTE_ADDR'];
        $key = "api_rate_limit_{$ip}";
        $limit = 100; // requests per minute
        
        // Simple rate limiting (in production, use Redis or proper rate limiting)
        $cacheFile = sys_get_temp_dir() . "/api_rate_limit_" . md5($ip) . ".txt";
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            $currentMinute = floor(time() / 60);
            
            if ($data['minute'] === $currentMinute) {
                if ($data['count'] >= $limit) {
                    return false;
                }
                $data['count']++;
            } else {
                $data = ['minute' => $currentMinute, 'count' => 1];
            }
        } else {
            $data = ['minute' => floor(time() / 60), 'count' => 1];
        }
        
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
    
    /**
     * Handle authentication endpoints
     */
    private function handleAuth() {
        switch ($this->method) {
            case 'POST':
                switch ($this->verb) {
                    case 'login':
                        return $this->login();
                    case 'register':
                        return $this->register();
                    case 'refresh':
                        return $this->refreshToken();
                    case 'logout':
                        return $this->logout();
                    default:
                        return $this->response(['error' => 'Invalid auth action'], 400);
                }
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Login endpoint
     */
    private function login() {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            return $this->response(['error' => 'Email and password required'], 400);
        }
        
        // Rate limiting for login attempts
        if (!Security::checkRateLimit('api_login', 5, 300)) {
            return $this->response(['error' => 'Too many login attempts'], 429);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT id, email, password, role, status FROM users WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password'])) {
            Security::logSecurityEvent('api_login_failed', ['email' => $email], 'warning');
            return $this->response(['error' => 'Invalid credentials'], 401);
        }
        
        if ($user['status'] !== 'active') {
            return $this->response(['error' => 'Account not active'], 403);
        }
        
        // Generate JWT token
        $token = $this->generateJWTToken($user);
        
        // Log successful login
        Security::logSecurityEvent('api_login_success', ['user_id' => $user['id']], 'info');
        
        return $this->response([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ],
            'token' => $token,
            'expires_in' => 3600 // 1 hour
        ]);
    }
    
    /**
     * Generate JWT token
     */
    private function generateJWTToken($user) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'iat' => time(),
            'exp' => time() + 3600 // 1 hour
        ]);
        
        $headerEncoded = base64_encode($header);
        $payloadEncoded = base64_encode($payload);
        
        $signature = base64_encode(hash_hmac(
            'sha256',
            $headerEncoded . '.' . $payloadEncoded,
            $_ENV['JWT_SECRET'] ?? (static function() { throw new \RuntimeException('JWT_SECRET not set in .env'); })(),
            true
        ));
        
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
    }
    
    /**
     * Handle products endpoints
     */
    private function handleProducts() {
        switch ($this->method) {
            case 'GET':
                if (isset($this->args[0])) {
                    return $this->getProduct($this->args[0]);
                } else {
                    return $this->getProducts();
                }
            case 'POST':
                $this->requireAuth(['merchant', 'admin']);
                return $this->createProduct();
            case 'PUT':
                $this->requireAuth(['merchant', 'admin']);
                return $this->updateProduct($this->args[0]);
            case 'DELETE':
                $this->requireAuth(['merchant', 'admin']);
                return $this->deleteProduct($this->args[0]);
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get products with filtering and pagination
     */
    private function getProducts() {
        $page = intval($_GET['page'] ?? 1);
        $limit = min(50, intval($_GET['limit'] ?? 20));
        $category = $_GET['category'] ?? '';
        $search = $_GET['search'] ?? '';
        $merchant_id = intval($_GET['merchant_id'] ?? 0);
        $sort = $_GET['sort'] ?? 'created_at';
        $order = $_GET['order'] ?? 'desc';
        
        $offset = ($page - 1) * $limit;
        
        $whereConditions = ["p.status = 'active'"];
        $params = [];
        
        if ($category) {
            $whereConditions[] = "p.category = ?";
            $params[] = $category;
        }
        
        if ($search) {
            $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
        }
        
        if ($merchant_id > 0) {
            $whereConditions[] = "p.merchant_id = ?";
            $params[] = $merchant_id;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Validate sort column
        $allowedSorts = ['name', 'price', 'created_at', 'average_rating'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'created_at';
        }
        
        $orderClause = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*,
                u.email as merchant_email,
                CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as merchant_name
            FROM products p
            JOIN users u ON p.merchant_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE {$whereClause}
            ORDER BY p.{$sort} {$orderClause}
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        // Get total count
        $countStmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM products p WHERE {$whereClause}
        ");
        $countStmt->execute(array_slice($params, 0, -2));
        $totalCount = $countStmt->fetchColumn();
        
        return $this->response([
            'success' => true,
            'data' => $products,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalCount,
                'pages' => ceil($totalCount / $limit)
            ]
        ]);
    }
    
    /**
     * Get single product
     */
    private function getProduct($productId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*,
                u.email as merchant_email,
                CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as merchant_name
            FROM products p
            JOIN users u ON p.merchant_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE p.id = ? AND p.status = 'active'
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            return $this->response(['error' => 'Product not found'], 404);
        }
        
        return $this->response([
            'success' => true,
            'data' => $product
        ]);
    }
    
    /**
     * Require authentication with specific roles
     */
    private function requireAuth($allowedRoles = []) {
        if (!$this->userId) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        if (!empty($allowedRoles) && !in_array($this->userRole, $allowedRoles)) {
            http_response_code(403);
            echo json_encode(['error' => 'Insufficient permissions']);
            exit;
        }
    }
    
    /**
     * Standard API response
     */
    private function response($data, $status = 200) {
        http_response_code($status);
        echo json_encode($data);
        exit;
    }
    
    /**
     * Handle analytics endpoints
     */
    private function handleAnalytics() {
        $this->requireAuth(); // Analytics require authentication
        
        switch ($this->method) {
            case 'GET':
                switch ($this->verb) {
                    case 'merchant':
                        return $this->getMerchantAnalytics();
                    case 'platform':
                        $this->requireAuth(['admin']);
                        return $this->getPlatformAnalytics();
                    case 'products':
                        return $this->getProductAnalytics();
                    case 'sales':
                        return $this->getSalesAnalytics();
                    case 'customers':
                        return $this->getCustomerAnalytics();
                    case 'realtime':
                        return $this->getRealtimeAnalytics();
                    default:
                        return $this->getAnalyticsOverview();
                }
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get merchant analytics for mobile app
     */
    private function getMerchantAnalytics() {
        if ($this->userRole !== 'merchant') {
            return $this->response(['error' => 'Access denied'], 403);
        }
        
        $period = $_GET['period'] ?? '30_days';
        $metrics = $_GET['metrics'] ?? 'overview';
        
        require_once '../includes/AnalyticsSystem.php';
        $analytics = new AnalyticsSystem($this->pdo);
        
        switch ($metrics) {
            case 'overview':
                $data = $analytics->getMerchantAnalytics($this->userId, $period);
                return $this->response([
                    'success' => true,
                    'data' => [
                        'sales_overview' => $data['sales_overview'],
                        'revenue_trends' => array_slice($data['revenue_trends'], -7), // Last 7 days for mobile
                        'top_products' => array_slice($data['product_performance'], 0, 5),
                        'conversion_metrics' => $data['conversion_metrics']
                    ]
                ]);
                
            case 'products':
                $data = $analytics->getMerchantAnalytics($this->userId, $period);
                return $this->response([
                    'success' => true,
                    'data' => $data['product_performance']
                ]);
                
            case 'customers':
                $data = $analytics->getMerchantAnalytics($this->userId, $period);
                return $this->response([
                    'success' => true,
                    'data' => $data['customer_insights']
                ]);
                
            case 'revenue':
                $data = $analytics->getMerchantAnalytics($this->userId, $period);
                return $this->response([
                    'success' => true,
                    'data' => [
                        'trends' => $data['revenue_trends'],
                        'commission' => $data['commission_analytics'],
                        'orders' => $data['order_analytics']
                    ]
                ]);
                
            default:
                return $this->response(['error' => 'Invalid metrics type'], 400);
        }
    }
    
    /**
     * Get platform analytics (admin only)
     */
    private function getPlatformAnalytics() {
        $period = $_GET['period'] ?? '30_days';
        
        require_once '../includes/AnalyticsSystem.php';
        $analytics = new AnalyticsSystem($this->pdo);
        $data = $analytics->getPlatformAnalytics($period);
        
        return $this->response([
            'success' => true,
            'data' => $data
        ]);
    }
    
    /**
     * Get product analytics
     */
    private function getProductAnalytics() {
        $productId = $_GET['product_id'] ?? null;
        $period = $_GET['period'] ?? '30_days';
        
        if (!$productId) {
            return $this->response(['error' => 'Product ID required'], 400);
        }
        
        // Check if user owns this product (for merchants) or is admin
        if ($this->userRole === 'merchant') {
            $stmt = $this->pdo->prepare("SELECT id FROM products WHERE id = ? AND merchant_id = ?");
            $stmt->execute([$productId, $this->userId]);
            if (!$stmt->fetch()) {
                return $this->response(['error' => 'Product not found'], 404);
            }
        }
        
        $dateRange = $this->getDateRange($period);
        
        // Get product performance metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.price,
                p.views_count,
                p.conversion_rate,
                COUNT(DISTINCT oi.order_id) as orders_count,
                SUM(oi.quantity) as units_sold,
                SUM(oi.quantity * oi.price) as revenue,
                AVG(pr.rating) as average_rating,
                COUNT(DISTINCT pr.id) as review_count
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at BETWEEN ? AND ?
            LEFT JOIN product_reviews pr ON p.id = pr.product_id
            WHERE p.id = ?
            GROUP BY p.id
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end'], $productId]);
        $productData = $stmt->fetch();
        
        // Get daily views trend
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as views
            FROM product_views
            WHERE product_id = ? AND created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$productId, $dateRange['start'], $dateRange['end']]);
        $viewsTrend = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => [
                'product' => $productData,
                'views_trend' => $viewsTrend
            ]
        ]);
    }
    
    /**
     * Get sales analytics
     */
    private function getSalesAnalytics() {
        $period = $_GET['period'] ?? '30_days';
        $dateRange = $this->getDateRange($period);
        
        // Build query based on user role
        $whereClause = '';
        $params = [$dateRange['start'], $dateRange['end']];
        
        if ($this->userRole === 'merchant') {
            $whereClause = 'AND o.merchant_id = ?';
            $params[] = $this->userId;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as orders_count,
                SUM(o.total_amount) as revenue,
                AVG(o.total_amount) as avg_order_value,
                COUNT(DISTINCT o.customer_id) as unique_customers
            FROM orders o
            WHERE o.created_at BETWEEN ? AND ? $whereClause
            GROUP BY DATE(o.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute($params);
        $salesData = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $salesData
        ]);
    }
    
    /**
     * Get customer analytics
     */
    private function getCustomerAnalytics() {
        $period = $_GET['period'] ?? '30_days';
        $dateRange = $this->getDateRange($period);
        
        $whereClause = '';
        $params = [$dateRange['start'], $dateRange['end']];
        
        if ($this->userRole === 'merchant') {
            $whereClause = 'AND o.merchant_id = ?';
            $params[] = $this->userId;
        }
        
        // Customer acquisition and retention metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.customer_id) as total_customers,
                COUNT(DISTINCT CASE WHEN order_count.cnt > 1 THEN o.customer_id END) as returning_customers,
                AVG(customer_ltv.lifetime_value) as avg_customer_ltv
            FROM orders o
            JOIN (
                SELECT customer_id, COUNT(*) as cnt
                FROM orders
                WHERE created_at BETWEEN ? AND ? $whereClause
                GROUP BY customer_id
            ) order_count ON o.customer_id = order_count.customer_id
            JOIN (
                SELECT customer_id, SUM(total_amount) as lifetime_value
                FROM orders
                GROUP BY customer_id
            ) customer_ltv ON o.customer_id = customer_ltv.customer_id
            WHERE o.created_at BETWEEN ? AND ? $whereClause
        ");
        $stmt->execute(array_merge($params, $params));
        $customerData = $stmt->fetch();
        
        return $this->response([
            'success' => true,
            'data' => $customerData
        ]);
    }
    
    /**
     * Get real-time analytics
     */
    private function getRealtimeAnalytics() {
        // Real-time metrics for dashboards
        $realTimeData = [];
        
        // Today's sales (last 24 hours)
        $whereClause = '';
        $params = [];
        
        if ($this->userRole === 'merchant') {
            $whereClause = 'WHERE merchant_id = ?';
            $params[] = $this->userId;
        }
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as todays_orders,
                COALESCE(SUM(total_amount), 0) as todays_revenue,
                COUNT(DISTINCT customer_id) as todays_customers
            FROM orders 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) $whereClause
        ");
        $stmt->execute($params);
        $todaysData = $stmt->fetch();
        
        // Active sessions (last hour)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as active_sessions
            FROM user_sessions 
            WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $sessionsData = $stmt->fetch();
        
        return $this->response([
            'success' => true,
            'data' => [
                'todays_metrics' => $todaysData,
                'active_sessions' => $sessionsData['active_sessions'],
                'timestamp' => date('c')
            ]
        ]);
    }
    
    /**
     * Get analytics overview
     */
    private function getAnalyticsOverview() {
        $period = $_GET['period'] ?? '7_days';
        
        if ($this->userRole === 'merchant') {
            return $this->getMerchantAnalytics();
        } elseif ($this->userRole === 'admin') {
            return $this->getPlatformAnalytics();
        } else {
            // Customer analytics - limited view
            return $this->getCustomerPersonalAnalytics();
        }
    }
    
    /**
     * Get customer's personal analytics
     */
    private function getCustomerPersonalAnalytics() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_spent,
                AVG(total_amount) as avg_order_value,
                MIN(created_at) as first_order,
                MAX(created_at) as last_order
            FROM orders 
            WHERE customer_id = ?
        ");
        $stmt->execute([$this->userId]);
        $customerData = $stmt->fetch();
        
        return $this->response([
            'success' => true,
            'data' => $customerData
        ]);
    }
    
    /**
     * Utility method to get date range
     */
    private function getDateRange($period) {
        $end = new DateTime();
        $start = clone $end;
        
        switch ($period) {
            case '7_days':
                $start->modify('-7 days');
                break;
            case '30_days':
                $start->modify('-30 days');
                break;
            case '90_days':
                $start->modify('-90 days');
                break;
            case '1_year':
                $start->modify('-1 year');
                break;
            default:
                $start->modify('-30 days');
        }
        
        return [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Handle orders endpoints for mobile
     */
    private function handleOrders() {
        switch ($this->method) {
            case 'GET':
                if (isset($this->args[0])) {
                    return $this->getOrder($this->args[0]);
                } else {
                    return $this->getOrders();
                }
            case 'POST':
                $this->requireAuth(['customer']);
                return $this->createOrder();
            case 'PUT':
                $this->requireAuth();
                return $this->updateOrder($this->args[0]);
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get orders list for mobile
     */
    private function getOrders() {
        $this->requireAuth();
        
        $page = intval($_GET['page'] ?? 1);
        $limit = min(50, intval($_GET['limit'] ?? 20));
        $status = $_GET['status'] ?? '';
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        // Filter by user role
        if ($this->userRole === 'customer') {
            $whereConditions[] = "o.customer_id = ?";
            $params[] = $this->userId;
        } elseif ($this->userRole === 'merchant') {
            $whereConditions[] = "o.merchant_id = ?";
            $params[] = $this->userId;
        }
        
        if ($status) {
            $whereConditions[] = "o.status = ?";
            $params[] = $status;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                o.*,
                customer.email as customer_email,
                merchant.email as merchant_email,
                COUNT(oi.id) as item_count
            FROM orders o
            JOIN users customer ON o.customer_id = customer.id
            JOIN users merchant ON o.merchant_id = merchant.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE {$whereClause}
            GROUP BY o.id
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $orders,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($orders)
            ]
        ]);
    }
    
    /**
     * Get single order details
     */
    private function getOrder($orderId) {
        $this->requireAuth();
        
        $stmt = $this->pdo->prepare("
            SELECT o.*, customer.email as customer_email, merchant.email as merchant_email
            FROM orders o
            JOIN users customer ON o.customer_id = customer.id
            JOIN users merchant ON o.merchant_id = merchant.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) {
            return $this->response(['error' => 'Order not found'], 404);
        }
        
        // Check permissions
        if ($this->userRole !== 'admin' && 
            $order['customer_id'] !== $this->userId && 
            $order['merchant_id'] !== $this->userId) {
            return $this->response(['error' => 'Access denied'], 403);
        }
        
        // Get order items
        $stmt = $this->pdo->prepare("
            SELECT oi.*, p.name as product_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $stmt->execute([$orderId]);
        $order['items'] = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $order
        ]);
    }
    
    /**
     * Create new order
     */
    private function createOrder() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $items = $input['items'] ?? [];
        
        if (empty($items)) {
            return $this->response(['error' => 'Order items are required'], 400);
        }
        
        try {
            $this->pdo->beginTransaction();
            
            // Group items by merchant
            $merchantGroups = [];
            foreach ($items as $item) {
                $stmt = $this->pdo->prepare("SELECT merchant_id, price FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    throw new Exception("Product not found: {$item['product_id']}");
                }
                
                $merchantId = $product['merchant_id'];
                if (!isset($merchantGroups[$merchantId])) {
                    $merchantGroups[$merchantId] = [];
                }
                
                $merchantGroups[$merchantId][] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $product['price']
                ];
            }
            
            $orderIds = [];
            
            // Create separate orders for each merchant
            foreach ($merchantGroups as $merchantId => $merchantItems) {
                $totalAmount = 0;
                foreach ($merchantItems as $item) {
                    $totalAmount += $item['price'] * $item['quantity'];
                }
                
                // Create order
                $stmt = $this->pdo->prepare("
                    INSERT INTO orders (customer_id, merchant_id, total_amount, status, created_at)
                    VALUES (?, ?, ?, 'pending_payment', NOW())
                ");
                $stmt->execute([$this->userId, $merchantId, $totalAmount]);
                $orderId = $this->pdo->lastInsertId();
                
                // Add order items
                foreach ($merchantItems as $item) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, price)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $orderId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price']
                    ]);
                }
                
                $orderIds[] = $orderId;
            }
            
            $this->pdo->commit();
            
            return $this->response([
                'success' => true,
                'message' => 'Orders created successfully',
                'order_ids' => $orderIds
            ]);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $this->response(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Update order status
     */
    private function updateOrder($orderId) {
        $input = json_decode(file_get_contents('php://input'), true);
        $status = $input['status'] ?? '';
        
        if (!$status) {
            return $this->response(['error' => 'Status is required'], 400);
        }
        
        $stmt = $this->pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $orderId]);
        
        return $this->response([
            'success' => true,
            'message' => 'Order status updated successfully'
        ]);
    }
    
    /**
     * Handle users endpoints
     */
    private function handleUsers() {
        switch ($this->method) {
            case 'GET':
                if ($this->verb === 'profile') {
                    return $this->getUserProfile();
                } else {
                    $this->requireAuth(['admin']);
                    return $this->getUsers();
                }
            case 'PUT':
                if ($this->verb === 'profile') {
                    return $this->updateUserProfile();
                }
                return $this->response(['error' => 'Invalid action'], 400);
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get user profile
     */
    private function getUserProfile() {
        $this->requireAuth();
        
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id, u.email, u.role, u.status, u.created_at,
                p.first_name, p.last_name, p.phone, p.address, p.bio
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            WHERE u.id = ?
        ");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        
        return $this->response([
            'success' => true,
            'data' => $user
        ]);
    }
    
    /**
     * Update user profile
     */
    private function updateUserProfile() {
        $this->requireAuth();
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, phone, address, bio)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name = VALUES(last_name),
                    phone = VALUES(phone),
                    address = VALUES(address),
                    bio = VALUES(bio),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $this->userId,
                $input['first_name'] ?? '',
                $input['last_name'] ?? '',
                $input['phone'] ?? '',
                $input['address'] ?? '',
                $input['bio'] ?? ''
            ]);
            
            return $this->response([
                'success' => true,
                'message' => 'Profile updated successfully'
            ]);
            
        } catch (Exception $e) {
            return $this->response(['error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Handle merchants endpoints
     */
    private function handleMerchants() {
        switch ($this->method) {
            case 'GET':
                if (isset($this->args[0])) {
                    return $this->getMerchant($this->args[0]);
                } else {
                    return $this->getMerchants();
                }
            case 'PUT':
                $this->requireAuth(['merchant']);
                return $this->updateMerchant($this->args[0]);
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get merchants list
     */
    private function getMerchants() {
        $page = intval($_GET['page'] ?? 1);
        $limit = min(50, intval($_GET['limit'] ?? 20));
        $category = $_GET['category'] ?? '';
        $offset = ($page - 1) * $limit;
        
        $whereConditions = ["u.role = 'merchant'", "u.status = 'active'"];
        $params = [];
        
        if ($category) {
            $whereConditions[] = "EXISTS (SELECT 1 FROM products p WHERE p.merchant_id = u.id AND p.category = ?)";
            $params[] = $category;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id, u.email, u.created_at,
                CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) as name,
                p.bio,
                COUNT(DISTINCT pr.id) as product_count,
                AVG(r.rating) as average_rating,
                COUNT(DISTINCT r.id) as review_count
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN products pr ON u.id = pr.merchant_id
            LEFT JOIN product_reviews r ON pr.id = r.product_id
            WHERE {$whereClause}
            GROUP BY u.id
            ORDER BY average_rating DESC, product_count DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $merchants = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $merchants,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($merchants)
            ]
        ]);
    }
    
    /**
     * Get single merchant details
     */
    private function getMerchant($merchantId) {
        $stmt = $this->pdo->prepare("
            SELECT 
                u.id, u.email, u.created_at,
                CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) as name,
                p.bio, p.phone, p.address,
                COUNT(DISTINCT pr.id) as product_count,
                AVG(r.rating) as average_rating,
                COUNT(DISTINCT r.id) as review_count
            FROM users u
            LEFT JOIN user_profiles p ON u.id = p.user_id
            LEFT JOIN products pr ON u.id = pr.merchant_id
            LEFT JOIN product_reviews r ON pr.id = r.product_id
            WHERE u.id = ? AND u.role = 'merchant'
            GROUP BY u.id
        ");
        $stmt->execute([$merchantId]);
        $merchant = $stmt->fetch();
        
        if (!$merchant) {
            return $this->response(['error' => 'Merchant not found'], 404);
        }
        
        // Get recent products
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*,
                AVG(r.rating) as average_rating,
                COUNT(r.id) as review_count
            FROM products p
            LEFT JOIN product_reviews r ON p.id = r.product_id
            WHERE p.merchant_id = ? AND p.status = 'active'
            GROUP BY p.id
            ORDER BY p.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$merchantId]);
        $merchant['recent_products'] = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $merchant
        ]);
    }
    
    /**
     * Handle reviews endpoints
     */
    private function handleReviews() {
        switch ($this->method) {
            case 'GET':
                return $this->getReviews();
            case 'POST':
                $this->requireAuth(['customer']);
                return $this->createReview();
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get reviews
     */
    private function getReviews() {
        $productId = $_GET['product_id'] ?? null;
        $page = intval($_GET['page'] ?? 1);
        $limit = min(50, intval($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        if ($productId) {
            $whereConditions[] = "r.product_id = ?";
            $params[] = $productId;
        }
        
        $whereClause = empty($whereConditions) ? '1=1' : implode(' AND ', $whereConditions);
        
        $stmt = $this->pdo->prepare("
            SELECT 
                r.*,
                u.email as user_email,
                CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) as user_name,
                pr.name as product_name
            FROM product_reviews r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN user_profiles p ON u.id = p.user_id
            JOIN products pr ON r.product_id = pr.id
            WHERE {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $reviews = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $reviews
        ]);
    }
    
    /**
     * Create review
     */
    private function createReview() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $productId = $input['product_id'] ?? null;
        $rating = intval($input['rating'] ?? 0);
        $reviewText = $input['review_text'] ?? '';
        
        if (!$productId || $rating < 1 || $rating > 5) {
            return $this->response(['error' => 'Invalid review data'], 400);
        }
        
        // Check if user has purchased this product
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.product_id = ? AND o.customer_id = ? AND o.status = 'completed'
        ");
        $stmt->execute([$productId, $this->userId]);
        $hasPurchased = $stmt->fetchColumn() > 0;
        
        if (!$hasPurchased) {
            return $this->response(['error' => 'You can only review products you have purchased'], 400);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO product_reviews (product_id, user_id, rating, review_text, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$productId, $this->userId, $rating, $reviewText]);
        
        return $this->response([
            'success' => true,
            'message' => 'Review created successfully',
            'review_id' => $this->pdo->lastInsertId()
        ]);
    }
    
    /**
     * Handle messages endpoints
     */
    private function handleMessages() {
        switch ($this->method) {
            case 'GET':
                if ($this->verb === 'conversations') {
                    return $this->getConversations();
                } else {
                    return $this->getMessages();
                }
            case 'POST':
                $this->requireAuth();
                return $this->sendMessage();
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get conversations list
     */
    private function getConversations() {
        $this->requireAuth();
        
        $stmt = $this->pdo->prepare("
            SELECT 
                CASE 
                    WHEN m.sender_id = ? THEN m.recipient_id 
                    ELSE m.sender_id 
                END as other_user_id,
                u.email as other_user_email,
                MAX(m.created_at) as last_message_time,
                COUNT(CASE WHEN m.recipient_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
            FROM messages m
            JOIN users u ON u.id = CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END
            WHERE m.sender_id = ? OR m.recipient_id = ?
            GROUP BY other_user_id
            ORDER BY last_message_time DESC
        ");
        $stmt->execute(array_fill(0, 5, $this->userId));
        $conversations = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $conversations
        ]);
    }
    
    /**
     * Get messages
     */
    private function getMessages() {
        $this->requireAuth();
        
        $otherUserId = $_GET['user_id'] ?? null;
        if (!$otherUserId) {
            return $this->response(['error' => 'User ID required'], 400);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND recipient_id = ?) OR (sender_id = ? AND recipient_id = ?)
            ORDER BY created_at ASC
        ");
        $stmt->execute([$this->userId, $otherUserId, $otherUserId, $this->userId]);
        $messages = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $messages
        ]);
    }
    
    /**
     * Send message
     */
    private function sendMessage() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $recipientId = $input['recipient_id'] ?? null;
        $messageText = $input['message_text'] ?? '';
        
        if (!$recipientId || empty($messageText)) {
            return $this->response(['error' => 'Recipient and message text are required'], 400);
        }
        
        $stmt = $this->pdo->prepare("
            INSERT INTO messages (sender_id, recipient_id, message_text, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$this->userId, $recipientId, $messageText]);
        
        return $this->response([
            'success' => true,
            'message' => 'Message sent successfully',
            'message_id' => $this->pdo->lastInsertId()
        ]);
    }
    
    /**
     * Handle payments endpoints
     */
    private function handlePayments() {
        switch ($this->method) {
            case 'GET':
                if ($this->verb === 'methods') {
                    return $this->getPaymentMethods();
                } else {
                    return $this->getPaymentTransactions();
                }
            case 'POST':
                $this->requireAuth(['customer']);
                if ($this->verb === 'process') {
                    return $this->processPayment();
                }
                return $this->response(['error' => 'Invalid action'], 400);
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Get available payment methods
     */
    private function getPaymentMethods() {
        require_once '../includes/PaymentGateway.php';
        $paymentGateway = new PaymentGateway($this->pdo);
        $config = $paymentGateway->getPaymentMethodsConfig();
        
        return $this->response([
            'success' => true,
            'data' => $config
        ]);
    }
    
    /**
     * Get payment transactions
     */
    private function getPaymentTransactions() {
        $this->requireAuth();
        
        $stmt = $this->pdo->prepare("
            SELECT pt.*, o.total_amount as order_amount
            FROM payment_transactions pt
            JOIN orders o ON pt.order_id = o.id
            WHERE o.customer_id = ? OR o.merchant_id = ?
            ORDER BY pt.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$this->userId, $this->userId]);
        $transactions = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $transactions
        ]);
    }
    
    /**
     * Process payment for order
     */
    private function processPayment() {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $orderId = $input['order_id'] ?? null;
        $paymentMethod = $input['payment_method'] ?? 'stripe';
        $paymentData = $input['payment_data'] ?? [];
        
        if (!$orderId) {
            return $this->response(['error' => 'Order ID is required'], 400);
        }
        
        // Verify order ownership
        $stmt = $this->pdo->prepare("SELECT customer_id, status FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order || $order['customer_id'] !== $this->userId) {
            return $this->response(['error' => 'Order not found'], 404);
        }
        
        if ($order['status'] !== 'pending_payment') {
            return $this->response(['error' => 'Order is not in payable state'], 400);
        }
        
        require_once '../includes/PaymentGateway.php';
        $paymentGateway = new PaymentGateway($this->pdo);
        $result = $paymentGateway->processPayment($orderId, $paymentMethod, $paymentData);
        
        return $this->response($result);
    }
    
    /**
     * Handle search endpoints
     */
    private function handleSearch() {
        switch ($this->method) {
            case 'GET':
                if ($this->verb === 'suggestions') {
                    return $this->getSearchSuggestions();
                } else {
                    return $this->searchProducts();
                }
            default:
                return $this->response(['error' => 'Method not allowed'], 405);
        }
    }
    
    /**
     * Search products
     */
    private function searchProducts() {
        $query = $_GET['q'] ?? '';
        $category = $_GET['category'] ?? '';
        $minPrice = floatval($_GET['min_price'] ?? 0);
        $maxPrice = floatval($_GET['max_price'] ?? 0);
        $sort = $_GET['sort'] ?? 'relevance';
        $page = intval($_GET['page'] ?? 1);
        $limit = min(50, intval($_GET['limit'] ?? 20));
        $offset = ($page - 1) * $limit;
        
        if (empty($query) && empty($category)) {
            return $this->response(['error' => 'Search query or category is required'], 400);
        }
        
        $whereConditions = ["p.status = 'active'"];
        $params = [];
        
        if ($query) {
            $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
            $params[] = "%{$query}%";
            $params[] = "%{$query}%";
        }
        
        if ($category) {
            $whereConditions[] = "p.category = ?";
            $params[] = $category;
        }
        
        if ($minPrice > 0) {
            $whereConditions[] = "p.price >= ?";
            $params[] = $minPrice;
        }
        
        if ($maxPrice > 0) {
            $whereConditions[] = "p.price <= ?";
            $params[] = $maxPrice;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        // Determine sort order
        $orderClause = match($sort) {
            'price_low' => 'p.price ASC',
            'price_high' => 'p.price DESC',
            'rating' => 'average_rating DESC',
            'newest' => 'p.created_at DESC',
            default => 'p.created_at DESC'
        };
        
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*,
                u.email as merchant_email,
                AVG(pr.rating) as average_rating,
                COUNT(pr.id) as review_count
            FROM products p
            JOIN users u ON p.merchant_id = u.id
            LEFT JOIN product_reviews pr ON p.id = pr.product_id
            WHERE {$whereClause}
            GROUP BY p.id
            ORDER BY {$orderClause}
            LIMIT ? OFFSET ?
        ");
        
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        return $this->response([
            'success' => true,
            'data' => $products,
            'query' => $query,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => count($products)
            ]
        ]);
    }
    
    /**
     * Get search suggestions
     */
    private function getSearchSuggestions() {
        $query = $_GET['q'] ?? '';
        
        if (strlen($query) < 2) {
            return $this->response([
                'success' => true,
                'data' => []
            ]);
        }
        
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT name as suggestion
            FROM products 
            WHERE name LIKE ? AND status = 'active'
            ORDER BY name ASC
            LIMIT 10
        ");
        $stmt->execute(["%{$query}%"]);
        $suggestions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $this->response([
            'success' => true,
            'data' => $suggestions
        ]);
    }
}

// Create API tables if they don't exist
function createAPITables($pdo) {
    $sql = "
        CREATE TABLE IF NOT EXISTS api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            permissions JSON NULL,
            is_active BOOLEAN DEFAULT TRUE,
            last_used_at TIMESTAMP NULL,
            usage_count INT DEFAULT 0,
            rate_limit INT DEFAULT 1000,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_api_key (api_key),
            INDEX idx_user_api_keys (user_id)
        );
        
        CREATE TABLE IF NOT EXISTS api_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            api_key_id INT NULL,
            endpoint VARCHAR(100) NOT NULL,
            method VARCHAR(10) NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            request_data JSON NULL,
            response_code INT,
            response_time FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE SET NULL,
            INDEX idx_api_logs_endpoint (endpoint),
            INDEX idx_api_logs_user (user_id),
            INDEX idx_api_logs_date (created_at)
        );
    ";
    
    $pdo->exec($sql);
}
?>