<?php
/**
 * Advanced Analytics System for VentDepot Marketplace
 * Provides comprehensive business intelligence and reporting capabilities
 */

require_once 'security.php';

class AnalyticsSystem {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get comprehensive merchant analytics
     */
    public function getMerchantAnalytics($merchantId, $period = '30_days') {
        $dateRange = $this->getDateRange($period);
        
        return [
            'sales_overview' => $this->getMerchantSalesOverview($merchantId, $dateRange),
            'product_performance' => $this->getProductPerformance($merchantId, $dateRange),
            'customer_insights' => $this->getCustomerInsights($merchantId, $dateRange),
            'revenue_trends' => $this->getRevenueTrends($merchantId, $dateRange),
            'order_analytics' => $this->getOrderAnalytics($merchantId, $dateRange),
            'review_analytics' => $this->getReviewAnalytics($merchantId, $dateRange),
            'commission_analytics' => $this->getCommissionAnalytics($merchantId, $dateRange),
            'conversion_metrics' => $this->getConversionMetrics($merchantId, $dateRange)
        ];
    }
    
    /**
     * Get platform-wide admin analytics
     */
    public function getPlatformAnalytics($period = '30_days') {
        $dateRange = $this->getDateRange($period);
        
        return [
            'platform_overview' => $this->getPlatformOverview($dateRange),
            'merchant_analytics' => $this->getAllMerchantAnalytics($dateRange),
            'category_performance' => $this->getCategoryPerformance($dateRange),
            'user_growth' => $this->getUserGrowthMetrics($dateRange),
            'financial_summary' => $this->getFinancialSummary($dateRange),
            'security_metrics' => $this->getSecurityMetrics($dateRange),
            'system_health' => $this->getSystemHealthMetrics($dateRange)
        ];
    }
    
    /**
     * Get merchant sales overview
     */
    private function getMerchantSalesOverview($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(total_amount) as gross_revenue,
                AVG(total_amount) as average_order_value,
                COUNT(DISTINCT user_id) as unique_customers
            FROM orders 
            WHERE merchant_id = ? 
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        $overview = $stmt->fetch();
        
        // Get previous period for comparison
        $previousRange = $this->getPreviousDateRange($dateRange);
        $stmt->execute([$merchantId, $previousRange['start'], $previousRange['end']]);
        $previousOverview = $stmt->fetch();
        
        // Calculate growth percentages
        $overview['growth_metrics'] = $this->calculateGrowthMetrics($overview, $previousOverview);
        
        return $overview;
    }
    
    /**
     * Get product performance analytics
     */
    private function getProductPerformance($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.id,
                p.name,
                p.category,
                p.price,
                COUNT(oi.id) as units_sold,
                SUM(oi.quantity * oi.price) as revenue,
                AVG(r.rating) as average_rating,
                COUNT(r.id) as review_count,
                p.views_count,
                ROUND((COUNT(oi.id) / NULLIF(p.views_count, 0)) * 100, 2) as conversion_rate
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at BETWEEN ? AND ?
            LEFT JOIN product_reviews r ON p.id = r.product_id
            WHERE p.merchant_id = ?
            GROUP BY p.id
            ORDER BY revenue DESC
            LIMIT 20
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end'], $merchantId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get customer insights
     */
    private function getCustomerInsights($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT o.user_id) as total_customers,
                COUNT(DISTINCT CASE WHEN order_count > 1 THEN o.user_id END) as returning_customers,
                AVG(customer_stats.lifetime_value) as avg_customer_ltv,
                AVG(customer_stats.order_frequency) as avg_order_frequency
            FROM orders o
            JOIN (
                SELECT 
                    user_id,
                    COUNT(*) as order_count,
                    SUM(total_amount) as lifetime_value,
                    COUNT(*) / DATEDIFF(MAX(created_at), MIN(created_at)) as order_frequency
                FROM orders 
                WHERE merchant_id = ?
                GROUP BY user_id
            ) customer_stats ON o.user_id = customer_stats.user_id
            WHERE o.merchant_id = ? 
            AND o.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$merchantId, $merchantId, $dateRange['start'], $dateRange['end']]);
        $insights = $stmt->fetch();
        
        // Get top customers
        $stmt = $this->pdo->prepare("
            SELECT 
                u.email,
                CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, '')) as name,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_spent,
                MAX(o.created_at) as last_order_date
            FROM orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN user_profiles up ON u.id = up.user_id
            WHERE o.merchant_id = ? AND o.created_at BETWEEN ? AND ?
            GROUP BY o.user_id
            ORDER BY total_spent DESC
            LIMIT 10
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        $insights['top_customers'] = $stmt->fetchAll();
        
        return $insights;
    }
    
    /**
     * Get revenue trends over time
     */
    private function getRevenueTrends($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as orders,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_order_value
            FROM orders 
            WHERE merchant_id = ? 
            AND created_at BETWEEN ? AND ?
            AND status = 'completed'
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get order analytics
     */
    private function getOrderAnalytics($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(total_amount) as total_value,
                AVG(total_amount) as avg_value
            FROM orders 
            WHERE merchant_id = ? 
            AND created_at BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        $statusBreakdown = $stmt->fetchAll();
        
        // Get order processing times
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(shipped_at, updated_at))) as avg_processing_hours,
                MIN(TIMESTAMPDIFF(HOUR, created_at, COALESCE(shipped_at, updated_at))) as min_processing_hours,
                MAX(TIMESTAMPDIFF(HOUR, created_at, COALESCE(shipped_at, updated_at))) as max_processing_hours
            FROM orders 
            WHERE merchant_id = ? 
            AND created_at BETWEEN ? AND ?
            AND status IN ('shipped', 'delivered', 'completed')
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        $processingTimes = $stmt->fetch();
        
        return [
            'status_breakdown' => $statusBreakdown,
            'processing_times' => $processingTimes
        ];
    }
    
    /**
     * Get review analytics
     */
    private function getReviewAnalytics($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                AVG(r.rating) as average_rating,
                COUNT(r.id) as total_reviews,
                COUNT(CASE WHEN r.rating = 5 THEN 1 END) as five_star_count,
                COUNT(CASE WHEN r.rating = 4 THEN 1 END) as four_star_count,
                COUNT(CASE WHEN r.rating = 3 THEN 1 END) as three_star_count,
                COUNT(CASE WHEN r.rating = 2 THEN 1 END) as two_star_count,
                COUNT(CASE WHEN r.rating = 1 THEN 1 END) as one_star_count
            FROM product_reviews r
            JOIN products p ON r.product_id = p.id
            WHERE p.merchant_id = ?
            AND r.created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        return $stmt->fetch();
    }
    
    /**
     * Get commission analytics for merchant
     */
    private function getCommissionAnalytics($merchantId, $dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(gross_amount) as total_gross_sales,
                SUM(commission_amount) as total_commission_paid,
                SUM(net_amount) as total_net_earnings,
                AVG(commission_rate) as avg_commission_rate,
                COUNT(*) as transaction_count
            FROM merchant_commissions 
            WHERE merchant_id = ? 
            AND created_at BETWEEN ? AND ?
        ");
        $stmt->execute([$merchantId, $dateRange['start'], $dateRange['end']]);
        return $stmt->fetch();
    }
    
    /**
     * Get conversion metrics
     */
    private function getConversionMetrics($merchantId, $dateRange) {
        // Get product views and purchases for conversion calculation
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(p.views_count) as total_views,
                COUNT(DISTINCT oi.order_id) as conversions,
                ROUND((COUNT(DISTINCT oi.order_id) / NULLIF(SUM(p.views_count), 0)) * 100, 2) as conversion_rate
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at BETWEEN ? AND ?
            WHERE p.merchant_id = ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end'], $merchantId]);
        return $stmt->fetch();
    }
    
    /**
     * Get platform overview for admin
     */
    private function getPlatformOverview($dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT u.id) as total_users,
                COUNT(DISTINCT CASE WHEN u.role = 'merchant' THEN u.id END) as total_merchants,
                COUNT(DISTINCT CASE WHEN u.role = 'customer' THEN u.id END) as total_customers,
                COUNT(DISTINCT p.id) as total_products,
                COUNT(DISTINCT o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                SUM(mc.commission_amount) as total_commission_collected
            FROM users u
            LEFT JOIN products p ON u.id = p.merchant_id
            LEFT JOIN orders o ON (u.id = o.customer_id OR u.id = o.merchant_id) 
                AND o.created_at BETWEEN ? AND ?
            LEFT JOIN merchant_commissions mc ON o.id = mc.order_id
            WHERE u.created_at <= ?
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end'], $dateRange['end']]);
        return $stmt->fetch();
    }
    
    /**
     * Get category performance
     */
    private function getCategoryPerformance($dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.category,
                COUNT(DISTINCT p.id) as product_count,
                COUNT(o.id) as orders,
                SUM(o.total_amount) as revenue,
                AVG(o.total_amount) as avg_order_value,
                AVG(pr.rating) as avg_rating
            FROM products p
            LEFT JOIN order_items oi ON p.id = oi.product_id
            LEFT JOIN orders o ON oi.order_id = o.id AND o.created_at BETWEEN ? AND ?
            LEFT JOIN product_reviews pr ON p.id = pr.product_id
            GROUP BY p.category
            ORDER BY revenue DESC
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get user growth metrics
     */
    private function getUserGrowthMetrics($dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as new_users,
                COUNT(CASE WHEN role = 'customer' THEN 1 END) as new_customers,
                COUNT(CASE WHEN role = 'merchant' THEN 1 END) as new_merchants
            FROM users 
            WHERE created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get financial summary for platform
     */
    private function getFinancialSummary($dateRange) {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(pt.amount) as total_processed,
                SUM(pt.platform_fee) as platform_fees_collected,
                SUM(mc.commission_amount) as commission_collected,
                COUNT(pt.id) as transaction_count,
                AVG(pt.amount) as avg_transaction_size
            FROM payment_transactions pt
            LEFT JOIN merchant_commissions mc ON pt.order_id = mc.order_id
            WHERE pt.created_at BETWEEN ? AND ?
            AND pt.status = 'completed'
        ");
        $stmt->execute([$dateRange['start'], $dateRange['end']]);
        return $stmt->fetch();
    }
    
    /**
     * Get security metrics
     */
    private function getSecurityMetrics($dateRange) {
        // This would integrate with the Security class for logging
        return [
            'failed_logins' => $this->getSecurityEventCount('login_failed', $dateRange),
            'blocked_ips' => $this->getSecurityEventCount('ip_blocked', $dateRange),
            'csrf_violations' => $this->getSecurityEventCount('csrf_violation', $dateRange),
            'api_errors' => $this->getSecurityEventCount('api_error', $dateRange)
        ];
    }
    
    /**
     * Get system health metrics
     */
    private function getSystemHealthMetrics($dateRange) {
        // Database performance metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_queries,
                AVG(execution_time) as avg_query_time
            FROM performance_logs 
            WHERE created_at BETWEEN ? AND ?
        ");
        
        // This would require a performance logging table
        return [
            'uptime_percentage' => 99.9, // Would be calculated from monitoring
            'avg_response_time' => 250, // milliseconds
            'error_rate' => 0.1, // percentage
            'active_sessions' => $this->getActiveSessionCount()
        ];
    }
    
    /**
     * Utility methods
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
    
    private function getPreviousDateRange($currentRange) {
        $start = new DateTime($currentRange['start']);
        $end = new DateTime($currentRange['end']);
        $diff = $start->diff($end);
        
        $prevEnd = clone $start;
        $prevStart = clone $prevEnd;
        $prevStart->sub($diff);
        
        return [
            'start' => $prevStart->format('Y-m-d H:i:s'),
            'end' => $prevEnd->format('Y-m-d H:i:s')
        ];
    }
    
    private function calculateGrowthMetrics($current, $previous) {
        $metrics = [];
        
        foreach ($current as $key => $value) {
            if (is_numeric($value) && isset($previous[$key])) {
                $prevValue = $previous[$key];
                if ($prevValue > 0) {
                    $growth = (($value - $prevValue) / $prevValue) * 100;
                    $metrics[$key . '_growth'] = round($growth, 2);
                } else {
                    $metrics[$key . '_growth'] = $value > 0 ? 100 : 0;
                }
            }
        }
        
        return $metrics;
    }
    
    private function getSecurityEventCount($eventType, $dateRange) {
        // This would integrate with security logging
        return 0; // Placeholder
    }
    
    private function getActiveSessionCount() {
        // Count active sessions from sessions table
        return 0; // Placeholder
    }
    
    /**
     * Export analytics data to various formats
     */
    public function exportAnalytics($data, $format = 'csv') {
        switch ($format) {
            case 'csv':
                return $this->exportToCSV($data);
            case 'json':
                return json_encode($data, JSON_PRETTY_PRINT);
            case 'pdf':
                return $this->exportToPDF($data);
            default:
                return false;
        }
    }
    
    private function exportToCSV($data) {
        // Implementation for CSV export
        return "CSV export functionality";
    }
    
    private function exportToPDF($data) {
        // Implementation for PDF export using a library like TCPDF
        return "PDF export functionality";
    }
}
?>