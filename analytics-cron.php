<?php
/**
 * Analytics Maintenance Cron Job
 * Scheduled task for calculating daily metrics and maintaining analytics data
 * 
 * Usage: php analytics-cron.php [date]
 * Schedule: Run daily at midnight
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/AnalyticsSystem.php';

// Disable time limit for long-running tasks
set_time_limit(0);

// Get target date (default to yesterday for end-of-day calculations)
$targetDate = isset($argv[1]) ? $argv[1] : date('Y-m-d', strtotime('-1 day'));

echo "[" . date('Y-m-d H:i:s') . "] Starting analytics maintenance for date: $targetDate\n";

try {
    $analytics = new AnalyticsSystem($pdo);
    
    // 1. Calculate merchant daily metrics
    echo "Calculating merchant daily metrics...\n";
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'merchant' AND status = 'active'");
    $stmt->execute();
    $merchants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $merchantCount = 0;
    foreach ($merchants as $merchantId) {
        try {
            // Use stored procedure if available, otherwise manual calculation
            $stmt = $pdo->prepare("CALL CalculateMerchantMetrics(?, ?)");
            $stmt->execute([$merchantId, $targetDate]);
            $merchantCount++;
        } catch (PDOException $e) {
            // Fallback to manual calculation
            calculateMerchantMetricsManually($pdo, $merchantId, $targetDate);
            $merchantCount++;
        }
    }
    echo "Processed $merchantCount merchants.\n";
    
    // 2. Calculate platform daily metrics
    echo "Calculating platform daily metrics...\n";
    try {
        $stmt = $pdo->prepare("CALL CalculatePlatformMetrics(?)");
        $stmt->execute([$targetDate]);
    } catch (PDOException $e) {
        calculatePlatformMetricsManually($pdo, $targetDate);
    }
    echo "Platform metrics calculated.\n";
    
    // 3. Update product metrics
    echo "Updating product metrics...\n";
    updateProductMetrics($pdo, $targetDate);
    echo "Product metrics updated.\n";
    
    // 4. Calculate category metrics
    echo "Calculating category metrics...\n";
    calculateCategoryMetrics($pdo, $targetDate);
    echo "Category metrics calculated.\n";
    
    // 5. Update user cohort data
    echo "Updating user cohort data...\n";
    updateUserCohorts($pdo, $targetDate);
    echo "User cohort data updated.\n";
    
    // 6. Clean up old data (keep last 2 years)
    echo "Cleaning up old analytics data...\n";
    cleanupOldData($pdo);
    echo "Data cleanup completed.\n";
    
    // 7. Update analytics cache
    echo "Refreshing analytics cache...\n";
    refreshAnalyticsCache($pdo);
    echo "Analytics cache refreshed.\n";
    
    // 8. Generate daily reports if configured
    if (getenv('SEND_DAILY_REPORTS') === 'true') {
        echo "Generating daily reports...\n";
        generateDailyReports($pdo, $targetDate);
        echo "Daily reports sent.\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Analytics maintenance completed successfully.\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    error_log("Analytics cron job failed: " . $e->getMessage());
    exit(1);
}

/**
 * Manual merchant metrics calculation
 */
function calculateMerchantMetricsManually($pdo, $merchantId, $date) {
    $stmt = $pdo->prepare("
        INSERT INTO merchant_daily_metrics (
            merchant_id, date, orders_count, orders_value, new_customers_count,
            average_order_value, commission_earned, reviews_received, average_rating
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            orders_count = VALUES(orders_count),
            orders_value = VALUES(orders_value),
            new_customers_count = VALUES(new_customers_count),
            average_order_value = VALUES(average_order_value),
            commission_earned = VALUES(commission_earned),
            reviews_received = VALUES(reviews_received),
            average_rating = VALUES(average_rating),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Get orders data
    $ordersStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as orders_count,
            COALESCE(SUM(total_amount), 0) as orders_value,
            COUNT(DISTINCT customer_id) as new_customers_count,
            COALESCE(AVG(total_amount), 0) as average_order_value
        FROM orders 
        WHERE merchant_id = ? AND DATE(created_at) = ?
    ");
    $ordersStmt->execute([$merchantId, $date]);
    $orderData = $ordersStmt->fetch();
    
    // Get commission data
    $commissionStmt = $pdo->prepare("
        SELECT COALESCE(SUM(commission_amount), 0) as commission_earned
        FROM merchant_commissions 
        WHERE merchant_id = ? AND DATE(created_at) = ?
    ");
    $commissionStmt->execute([$merchantId, $date]);
    $commissionData = $commissionStmt->fetch();
    
    // Get reviews data
    $reviewsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as reviews_received,
            COALESCE(AVG(rating), 0) as average_rating
        FROM product_reviews pr
        JOIN products p ON pr.product_id = p.id
        WHERE p.merchant_id = ? AND DATE(pr.created_at) = ?
    ");
    $reviewsStmt->execute([$merchantId, $date]);
    $reviewData = $reviewsStmt->fetch();
    
    $stmt->execute([
        $merchantId,
        $date,
        $orderData['orders_count'],
        $orderData['orders_value'],
        $orderData['new_customers_count'],
        $orderData['average_order_value'],
        $commissionData['commission_earned'],
        $reviewData['reviews_received'],
        $reviewData['average_rating']
    ]);
}

/**
 * Manual platform metrics calculation
 */
function calculatePlatformMetricsManually($pdo, $date) {
    $stmt = $pdo->prepare("
        INSERT INTO platform_daily_metrics (
            date, total_users, new_users, total_merchants, new_merchants,
            total_products, new_products, total_orders, orders_value,
            commission_collected, platform_fees_collected
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            total_users = VALUES(total_users),
            new_users = VALUES(new_users),
            total_merchants = VALUES(total_merchants),
            new_merchants = VALUES(new_merchants),
            total_products = VALUES(total_products),
            new_products = VALUES(new_products),
            total_orders = VALUES(total_orders),
            orders_value = VALUES(orders_value),
            commission_collected = VALUES(commission_collected),
            platform_fees_collected = VALUES(platform_fees_collected),
            updated_at = CURRENT_TIMESTAMP
    ");
    
    // Get various metrics
    $metricsStmt = $pdo->prepare("
        SELECT 
            (SELECT COUNT(*) FROM users WHERE DATE(created_at) <= ?) as total_users,
            (SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?) as new_users,
            (SELECT COUNT(*) FROM users WHERE role = 'merchant' AND DATE(created_at) <= ?) as total_merchants,
            (SELECT COUNT(*) FROM users WHERE role = 'merchant' AND DATE(created_at) = ?) as new_merchants,
            (SELECT COUNT(*) FROM products WHERE DATE(created_at) <= ?) as total_products,
            (SELECT COUNT(*) FROM products WHERE DATE(created_at) = ?) as new_products,
            (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?) as total_orders,
            (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = ?) as orders_value,
            (SELECT COALESCE(SUM(commission_amount), 0) FROM merchant_commissions WHERE DATE(created_at) = ?) as commission_collected,
            (SELECT COALESCE(SUM(platform_fee), 0) FROM payment_transactions WHERE DATE(created_at) = ?) as platform_fees_collected
    ");
    $metricsStmt->execute(array_fill(0, 10, $date));
    $metrics = $metricsStmt->fetch();
    
    $stmt->execute([
        $date,
        $metrics['total_users'],
        $metrics['new_users'],
        $metrics['total_merchants'],
        $metrics['new_merchants'],
        $metrics['total_products'],
        $metrics['new_products'],
        $metrics['total_orders'],
        $metrics['orders_value'],
        $metrics['commission_collected'],
        $metrics['platform_fees_collected']
    ]);
}

/**
 * Update product metrics
 */
function updateProductMetrics($pdo, $date) {
    // Update view counts from product_views table
    $stmt = $pdo->prepare("
        UPDATE products p 
        SET views_count = (
            SELECT COUNT(*) FROM product_views pv 
            WHERE pv.product_id = p.id AND DATE(pv.created_at) <= ?
        )
    ");
    $stmt->execute([$date]);
    
    // Update conversion rates
    $stmt = $pdo->prepare("
        UPDATE products p 
        SET conversion_rate = (
            SELECT ROUND((COUNT(DISTINCT oi.order_id) / NULLIF(p.views_count, 0)) * 100, 4)
            FROM order_items oi 
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.product_id = p.id AND DATE(o.created_at) <= ?
        )
        WHERE p.views_count > 0
    ");
    $stmt->execute([$date]);
}

/**
 * Calculate category metrics
 */
function calculateCategoryMetrics($pdo, $date) {
    $stmt = $pdo->prepare("
        INSERT INTO category_metrics (category, date, product_count, orders_count, revenue, views_count, average_price, average_rating)
        SELECT 
            p.category,
            ? as date,
            COUNT(DISTINCT p.id) as product_count,
            COUNT(DISTINCT o.id) as orders_count,
            COALESCE(SUM(o.total_amount), 0) as revenue,
            COALESCE(SUM(p.views_count), 0) as views_count,
            COALESCE(AVG(p.price), 0) as average_price,
            COALESCE(AVG(pr.rating), 0) as average_rating
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND DATE(o.created_at) = ?
        LEFT JOIN product_reviews pr ON p.id = pr.product_id
        WHERE p.category IS NOT NULL
        GROUP BY p.category
        ON DUPLICATE KEY UPDATE
            product_count = VALUES(product_count),
            orders_count = VALUES(orders_count),
            revenue = VALUES(revenue),
            views_count = VALUES(views_count),
            average_price = VALUES(average_price),
            average_rating = VALUES(average_rating),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$date, $date]);
}

/**
 * Update user cohort data
 */
function updateUserCohorts($pdo, $date) {
    $stmt = $pdo->prepare("
        INSERT INTO user_cohorts (user_id, cohort_month, months_since_signup, orders_count, revenue, is_active, last_order_date)
        SELECT 
            u.id,
            DATE_FORMAT(u.created_at, '%Y-%m-01') as cohort_month,
            PERIOD_DIFF(DATE_FORMAT(?, '%Y%m'), DATE_FORMAT(u.created_at, '%Y%m')) as months_since_signup,
            COUNT(o.id) as orders_count,
            COALESCE(SUM(o.total_amount), 0) as revenue,
            CASE WHEN MAX(o.created_at) >= DATE_SUB(?, INTERVAL 30 DAY) THEN 1 ELSE 0 END as is_active,
            MAX(DATE(o.created_at)) as last_order_date
        FROM users u
        LEFT JOIN orders o ON u.id = o.customer_id AND DATE(o.created_at) <= ?
        WHERE u.role = 'customer'
        GROUP BY u.id
        ON DUPLICATE KEY UPDATE
            orders_count = VALUES(orders_count),
            revenue = VALUES(revenue),
            is_active = VALUES(is_active),
            last_order_date = VALUES(last_order_date)
    ");
    $stmt->execute([$date, $date, $date]);
}

/**
 * Clean up old data
 */
function cleanupOldData($pdo) {
    $cutoffDate = date('Y-m-d', strtotime('-2 years'));
    
    $tables = [
        'product_views',
        'search_analytics',
        'user_sessions',
        'performance_logs',
        'analytics_cache'
    ];
    
    $allowedTables = array_flip(['product_views', 'search_analytics', 'user_sessions', 'performance_logs', 'analytics_cache']);
    foreach ($tables as $table) {
        if (!isset($allowedTables[$table])) {
            continue;
        }
        $stmt = $pdo->prepare("DELETE FROM `$table` WHERE created_at < ?");
        $stmt->execute([$cutoffDate]);
        echo "Cleaned up old data from $table.\n";
    }
}

/**
 * Refresh analytics cache
 */
function refreshAnalyticsCache($pdo) {
    // Clear expired cache entries
    $stmt = $pdo->prepare("DELETE FROM analytics_cache WHERE expires_at < NOW()");
    $stmt->execute();
    
    // Pre-calculate common metrics for faster dashboard loading
    $periods = ['7_days', '30_days', '90_days'];
    
    foreach ($periods as $period) {
        // This would pre-calculate and cache common analytics queries
        // Implementation depends on specific caching strategy
    }
}

/**
 * Generate daily reports
 */
function generateDailyReports($pdo, $date) {
    // This would generate and send daily analytics reports to merchants and admins
    // Implementation depends on email/notification system
    echo "Daily reports generation would be implemented here.\n";
}
?>