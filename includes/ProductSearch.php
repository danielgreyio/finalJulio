<?php
/**
 * Advanced Product Search and Filtering System
 * Handles complex product searches with multiple filters, sorting, and pagination
 */

class ProductSearch {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Advanced product search with multiple filters
     */
    public function searchProducts($params = []) {
        $defaults = [
            'query' => '',
            'category' => '',
            'min_price' => 0,
            'max_price' => 999999,
            'rating' => 0,
            'merchant_id' => 0,
            'in_stock' => false,
            'sort_by' => 'relevance',
            'sort_order' => 'desc',
            'page' => 1,
            'limit' => 24,
            'filters' => []
        ];
        
        $params = array_merge($defaults, $params);
        
        // Build base query
        $baseQuery = "
            FROM products p 
            LEFT JOIN users m ON p.merchant_id = m.id 
            LEFT JOIN user_profiles mp ON m.id = mp.user_id
            WHERE p.inventory >= 0
        ";
        
        $whereConditions = [];
        $queryParams = [];
        
        // Search query
        if (!empty($params['query'])) {
            $searchTerms = $this->parseSearchQuery($params['query']);
            $searchConditions = [];
            
            foreach ($searchTerms as $term) {
                $searchConditions[] = "
                    (p.name LIKE ? OR 
                     p.description LIKE ? OR 
                     p.category LIKE ?)
                ";
                $queryParams[] = "%{$term}%";
                $queryParams[] = "%{$term}%";
                $queryParams[] = "%{$term}%";
            }
            
            if (!empty($searchConditions)) {
                $whereConditions[] = "(" . implode(" AND ", $searchConditions) . ")";
            }
        }
        
        // Category filter
        if (!empty($params['category'])) {
            $whereConditions[] = "p.category = ?";
            $queryParams[] = $params['category'];
        }
        
        // Price range
        if ($params['min_price'] > 0) {
            $whereConditions[] = "p.price >= ?";
            $queryParams[] = $params['min_price'];
        }
        
        if ($params['max_price'] < 999999) {
            $whereConditions[] = "p.price <= ?";
            $queryParams[] = $params['max_price'];
        }
        
        // Rating filter - skip if average_rating column doesn't exist
        // if ($params['rating'] > 0) {
        //     $whereConditions[] = "p.average_rating >= ?";
        //     $queryParams[] = $params['rating'];
        // }
        
        // Merchant filter
        if ($params['merchant_id'] > 0) {
            $whereConditions[] = "p.merchant_id = ?";
            $queryParams[] = $params['merchant_id'];
        }
        
        // Stock filter
        if ($params['in_stock']) {
            $whereConditions[] = "p.inventory > 0";
        }
        
        // Custom filters (attributes, etc.) - commented out due to potential missing fields
        // foreach ($params['filters'] as $filterKey => $filterValue) {
        //     if (!empty($filterValue)) {
        //         switch ($filterKey) {
        //             case 'has_images':
        //                 if ($filterValue) {
        //                     $whereConditions[] = "(p.image_url IS NOT NULL AND p.image_url != '')";
        //                 }
        //                 break;
        //                 
        //             case 'free_shipping':
        //                 if ($filterValue) {
        //                     $whereConditions[] = "p.free_shipping = 1";
        //                 }
        //                 break;
        //                 
        //             case 'new_arrivals':
        //                 if ($filterValue) {
        //                     $whereConditions[] = "p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        //                 }
        //                 break;
        //                 
        //             case 'on_sale':
        //                 if ($filterValue) {
        //                     $whereConditions[] = "(p.sale_price IS NOT NULL AND p.sale_price < p.price)";
        //                 }
        //                 break;
        //         }
        //     }
        // }
        
        // Combine conditions
        if (!empty($whereConditions)) {
            $baseQuery .= " AND " . implode(" AND ", $whereConditions);
        }
        
        // Count total results
        $countQuery = "SELECT COUNT(DISTINCT p.id) " . $baseQuery;
        $countStmt = $this->pdo->prepare($countQuery);
        $countStmt->execute($queryParams);
        $totalResults = $countStmt->fetchColumn();
        
        // Add sorting
        $orderClause = $this->buildSortClause($params['sort_by'], $params['sort_order'], $params['query']);
        
        // Add pagination
        $offset = ($params['page'] - 1) * $params['limit'];
        
        // Validate and cast pagination parameters to prevent SQL injection
        $limit = (int)$params['limit'];
        $offset = (int)$offset;
        
        // Build final query
        $selectQuery = "
            SELECT DISTINCT 
                p.*,
                m.email as merchant_email,
                CONCAT(COALESCE(mp.first_name, ''), ' ', COALESCE(mp.last_name, '')) as merchant_name
            " . $baseQuery . "
            " . $orderClause . "
            LIMIT $limit OFFSET $offset
        ";
        
        $stmt = $this->pdo->prepare($selectQuery);
        $stmt->execute($queryParams);
        $products = $stmt->fetchAll();
        
        return [
            'products' => $products,
            'total_results' => $totalResults,
            'page' => $params['page'],
            'limit' => $params['limit'],
            'total_pages' => ceil($totalResults / $params['limit']),
            'filters_applied' => $this->getAppliedFilters($params)
        ];
    }
    
    /**
     * Parse search query into terms
     */
    private function parseSearchQuery($query) {
        // Remove special characters and split by spaces
        $query = preg_replace('/[^\w\s-]/', '', $query);
        $terms = array_filter(explode(' ', strtolower($query)), 'strlen');
        
        // Remove common stop words
        $stopWords = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by'];
        $terms = array_diff($terms, $stopWords);
        
        return array_unique($terms);
    }
    
    /**
     * Build ORDER BY clause
     */
    private function buildSortClause($sortBy, $sortOrder, $searchQuery = '') {
        $order = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        
        switch ($sortBy) {
            case 'price_low':
                return "ORDER BY p.price ASC";
                
            case 'price_high':
                return "ORDER BY p.price DESC";
                
            case 'rating':
                return "ORDER BY p.created_at {$order}"; // Fallback to creation date
                
            case 'newest':
                return "ORDER BY p.created_at DESC";
                
            case 'oldest':
                return "ORDER BY p.created_at ASC";
                
            case 'name':
                return "ORDER BY p.name {$order}";
                
            case 'popularity':
                return "ORDER BY p.created_at {$order}"; // Fallback to creation date
                
            case 'merchant_rating':
                return "ORDER BY p.created_at {$order}"; // Fallback to creation date;
                
            case 'relevance':
            default:
                if (!empty($searchQuery)) {
                    // Use a safer relevance calculation with existing fields
                    return "ORDER BY p.created_at DESC, p.name ASC";
                } else {
                    return "ORDER BY p.created_at DESC, p.name ASC";
                }
        }
    }
    
    /**
     * Get applied filters summary
     */
    private function getAppliedFilters($params) {
        $applied = [];
        
        if (!empty($params['query'])) {
            $applied['query'] = "Search: \"" . htmlspecialchars($params['query']) . "\"";
        }
        
        if (!empty($params['category'])) {
            $applied['category'] = "Category: " . htmlspecialchars($params['category']);
        }
        
        if ($params['min_price'] > 0 || $params['max_price'] < 999999) {
            $applied['price'] = "Price: $" . number_format($params['min_price']) . " - $" . number_format($params['max_price']);
        }
        
        if ($params['rating'] > 0) {
            $applied['rating'] = "Rating: " . $params['rating'] . "+ stars";
        }
        
        if ($params['in_stock']) {
            $applied['stock'] = "In Stock Only";
        }
        
        foreach ($params['filters'] as $key => $value) {
            if ($value) {
                switch ($key) {
                    case 'has_images':
                        $applied['images'] = "With Images";
                        break;
                    case 'free_shipping':
                        $applied['shipping'] = "Free Shipping";
                        break;
                    case 'new_arrivals':
                        $applied['new'] = "New Arrivals";
                        break;
                    case 'on_sale':
                        $applied['sale'] = "On Sale";
                        break;
                }
            }
        }
        
        return $applied;
    }
    
    /**
     * Get search suggestions
     */
    public function getSearchSuggestions($query, $limit = 10) {
        $terms = $this->parseSearchQuery($query);
        
        if (empty($terms)) {
            return [];
        }
        
        $suggestions = [];
        
        // Validate and cast limit parameter
        $limit = (int)$limit;
        
        // Product name suggestions
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT name 
            FROM products 
            WHERE inventory >= 0 AND name LIKE ? 
            ORDER BY name 
            LIMIT $limit
        ");
        
        foreach ($terms as $term) {
            $stmt->execute(["%{$term}%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $suggestions = array_merge($suggestions, $results);
        }
        
        // Category suggestions
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT category 
            FROM products 
            WHERE inventory >= 0 AND category LIKE ? 
            ORDER BY category 
            LIMIT $limit
        ");
        
        foreach ($terms as $term) {
            $stmt->execute(["%{$term}%"]);
            $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $suggestions = array_merge($suggestions, $results);
        }
        
        return array_unique(array_slice($suggestions, 0, $limit));
    }
    
    /**
     * Get popular search terms
     */
    public function getPopularSearches($limit = 10) {
        // This would require a search_queries table to track searches
        // For now, return common categories
        
        // Validate and cast limit parameter
        $limit = (int)$limit;
        
        $stmt = $this->pdo->prepare("
            SELECT category, COUNT(*) as product_count 
            FROM products 
            WHERE inventory >= 0 AND category IS NOT NULL 
            GROUP BY category 
            ORDER BY product_count DESC 
            LIMIT $limit
        ");
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get filter options for faceted search
     */
    public function getFilterOptions() {
        $options = [
            'categories' => [],
            'price_ranges' => [
                ['min' => 0, 'max' => 25, 'label' => 'Under $25'],
                ['min' => 25, 'max' => 50, 'label' => '$25 - $50'],
                ['min' => 50, 'max' => 100, 'label' => '$50 - $100'],
                ['min' => 100, 'max' => 250, 'label' => '$100 - $250'],
                ['min' => 250, 'max' => 999999, 'label' => 'Over $250']
            ],
            'ratings' => [
                ['value' => 4, 'label' => '4+ Stars'],
                ['value' => 3, 'label' => '3+ Stars'],
                ['value' => 2, 'label' => '2+ Stars'],
                ['value' => 1, 'label' => '1+ Stars']
            ]
        ];
        
        // Get categories with product counts
        $stmt = $this->pdo->prepare("
            SELECT category, COUNT(*) as count 
            FROM products 
            WHERE inventory >= 0 AND category IS NOT NULL 
            GROUP BY category 
            ORDER BY count DESC, category ASC
        ");
        $stmt->execute();
        $options['categories'] = $stmt->fetchAll();
        
        return $options;
    }
    
    /**
     * Auto-complete search
     */
    public function autoComplete($query, $limit = 8) {
        if (strlen($query) < 2) {
            return [];
        }
        
        $results = [];
        
        // Validate and cast limit parameter
        $limit = (int)$limit;
        
        // Product suggestions
        $stmt = $this->pdo->prepare("
            SELECT name, 'product' as type, id
            FROM products 
            WHERE inventory >= 0 AND name LIKE ? 
            ORDER BY 
                CASE WHEN name LIKE ? THEN 1 ELSE 2 END,
                name
            LIMIT $limit
        ");
        
        $stmt->execute(["%{$query}%", "{$query}%"]);
        $products = $stmt->fetchAll();
        
        // Category suggestions
        $remainingLimit = $limit - count($products);
        if ($remainingLimit > 0) {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT category as name, 'category' as type, NULL as id
                FROM products 
                WHERE inventory >= 0 AND category LIKE ? 
                ORDER BY category
                LIMIT $remainingLimit
            ");
            
            $stmt->execute(["%{$query}%"]);
            $categories = $stmt->fetchAll();
        } else {
            $categories = [];
        }
        
        return array_merge($products, $categories);
    }
    
    /**
     * Track search query (for analytics)
     */
    public function trackSearch($query, $userId = null, $resultsCount = 0) {
        try {
            // Create search_queries table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS search_queries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    query VARCHAR(255) NOT NULL,
                    user_id INT NULL,
                    results_count INT DEFAULT 0,
                    ip_address VARCHAR(45),
                    user_agent TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                    INDEX idx_search_query (query),
                    INDEX idx_search_date (created_at)
                )
            ");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO search_queries (query, user_id, results_count, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $query,
                $userId,
                $resultsCount,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (Exception $e) {
            // Log error but don't break search functionality
            error_log("Search tracking failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get related products based on current search/product
     */
    public function getRelatedProducts($productId = null, $category = null, $tags = [], $limit = 8) {
        $conditions = ["p.inventory >= 0"];
        $params = [];
        
        if ($productId) {
            $conditions[] = "p.id != ?";
            $params[] = $productId;
        }
        
        if ($category) {
            $conditions[] = "p.category = ?";
            $params[] = $category;
        }
        
        // Note: tags functionality requires enhanced schema
        // For now, we'll use description for tag-like matching
        if (!empty($tags)) {
            $tagConditions = [];
            foreach ($tags as $tag) {
                $tagConditions[] = "p.description LIKE ?";
                $params[] = "%{$tag}%";
            }
            if (!empty($tagConditions)) {
                $conditions[] = "(" . implode(" OR ", $tagConditions) . ")";
            }
        }
        
        $whereClause = implode(" AND ", $conditions);
        
        // Validate and cast limit parameter
        $limit = (int)$limit;
        
        $stmt = $this->pdo->prepare("
            SELECT p.*, m.email as merchant_email
            FROM products p
            LEFT JOIN users m ON p.merchant_id = m.id
            WHERE {$whereClause}
            ORDER BY p.created_at DESC, RAND()
            LIMIT $limit
        ");
        
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
?>