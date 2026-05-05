<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle product actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $productId = intval($_POST['product_id'] ?? 0);
    
    if ($action === 'toggle_status' && $productId) {
        // Toggle inventory between 0 and 1 to simulate active/inactive
        $stmt = $pdo->prepare("UPDATE products SET inventory = CASE WHEN inventory > 0 THEN 0 ELSE 1 END WHERE id = ?");
        if ($stmt->execute([$productId])) {
            $success = 'Product status updated successfully!';
        } else {
            $error = 'Failed to update product status.';
        }
    } elseif ($action === 'delete_product' && $productId) {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        if ($stmt->execute([$productId])) {
            $success = 'Product deleted successfully!';
        } else {
            $error = 'Failed to delete product.';
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';
$merchant = $_GET['merchant'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if ($search) {
    $whereConditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $whereConditions[] = "p.category = ?";
    $params[] = $category;
}

if ($status !== '') {
    if ($status == '1') {
        $whereConditions[] = "p.inventory > 0";
    } else {
        $whereConditions[] = "p.inventory = 0";
    }
}

if ($merchant) {
    $whereConditions[] = "p.merchant_id = ?";
    $params[] = $merchant;
}

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get products with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$productsQuery = "
    SELECT p.*, u.email as merchant_email,
           COALESCE(pd.weight_kg, 0) as weight_kg,
           COALESCE(pd.fragile, 0) as fragile,
           COALESCE(discounts.current_discount, 0) as current_discount,
           COALESCE(discounts.discount_type, '') as discount_type
    FROM products p
    LEFT JOIN users u ON p.merchant_id = u.id
    LEFT JOIN product_dimensions pd ON p.id = pd.product_id
    LEFT JOIN (
        SELECT product_id,
               discount_value as current_discount,
               discount_type
        FROM product_discounts
        WHERE is_active = 1 
        AND start_date <= NOW() 
        AND end_date >= NOW()
    ) discounts ON p.id = discounts.product_id
    $whereClause
    ORDER BY p.created_at DESC
    LIMIT $limit OFFSET $offset
";

$products = $pdo->prepare($productsQuery);
$products->execute($params);
$products = $products->fetchAll();

// Get total count for pagination
$countQuery = "SELECT COUNT(*) FROM products p LEFT JOIN users u ON p.merchant_id = u.id $whereClause";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $limit);

// Get categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get merchants for filter
$merchants = $pdo->query("SELECT id, email FROM users WHERE role = 'merchant' ORDER BY email")->fetchAll();

// Get statistics
$stats = [
    'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'active_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE inventory > 0")->fetchColumn(),
    'inactive_products' => $pdo->query("SELECT COUNT(*) FROM products WHERE inventory = 0")->fetchColumn(),
    'total_merchants' => $pdo->query("SELECT COUNT(DISTINCT merchant_id) FROM products")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Sidebar Backdrop -->
    <div x-data="{ sidebarOpen: false }" class="relative z-0 flex-1 flex flex-col overflow-hidden">
        <!-- Mobile Header -->
        <div class="md:hidden pl-1 pt-1 sm:pl-3 sm:pt-3 bg-white border-b border-gray-200">
            <button @click="sidebarOpen = !sidebarOpen" class="-ml-0.5 -mt-0.5 h-12 w-12 inline-flex items-center justify-center rounded-md text-gray-500 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500">
                <span class="sr-only">Open sidebar</span>
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Main Content -->
        <main class="flex-1 relative z-0 overflow-y-auto focus:outline-none">
            <div class="py-6">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Product Management</h1>
                            <p class="text-gray-600 mt-2">Manage all products across the platform</p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="seo-management.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-search mr-2"></i>SEO Management
                            </a>
                            <a href="pricing-management.php" class="bg-red-600 text-white px-4 py-2 rounded-md hover:bg-red-700">
                                <i class="fas fa-tags mr-2"></i>Price Management
                            </a>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-box text-blue-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Products</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_products']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Products</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['active_products']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-times-circle text-red-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Inactive Products</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['inactive_products']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-store text-purple-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Merchants</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_merchants']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Product name or description"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select name="category" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Status</option>
                                    <option value="1" <?= $status === '1' ? 'selected' : '' ?>>Active</option>
                                    <option value="0" <?= $status === '0' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Merchant</label>
                                <select name="merchant" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Merchants</option>
                                    <?php foreach ($merchants as $merch): ?>
                                        <option value="<?= $merch['id'] ?>" <?= $merchant == $merch['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($merch['email']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-search mr-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Products Table -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900">Products (<?= number_format($totalProducts) ?> total)</h2>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Merchant</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Stock</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="7" class="px-6 py-4 text-center text-gray-500">
                                                No products found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <?php if ($product['image_url']): ?>
                                                            <img class="h-10 w-10 rounded-lg object-cover mr-4" 
                                                                 src="<?= htmlspecialchars($product['image_url']) ?>" 
                                                                 alt="<?= htmlspecialchars($product['name']) ?>">
                                                        <?php else: ?>
                                                            <div class="h-10 w-10 bg-gray-200 rounded-lg flex items-center justify-center mr-4">
                                                                <i class="fas fa-image text-gray-400"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($product['name']) ?></div>
                                                            <div class="text-sm text-gray-500">ID: <?= $product['id'] ?></div>
                                                            <?php if ($product['fragile']): ?>
                                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                                    <i class="fas fa-exclamation-triangle mr-1"></i>Fragile
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($product['merchant_email']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    $<?= number_format($product['price'], 2) ?>
                                                    <?php if ($product['current_discount'] > 0): ?>
                                                        <div class="text-xs text-green-600 mt-1">
                                                            <?php if ($product['discount_type'] === 'percentage'): ?>
                                                                <?= $product['current_discount'] ?>% off
                                                            <?php else: ?>
                                                                $<?= $product['current_discount'] ?> off
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($product['inventory']) ?>
                                                    <?php if ($product['weight_kg']): ?>
                                                        <div class="text-xs text-gray-500"><?= $product['weight_kg'] ?>kg</div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?= $product['inventory'] > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                        <?= $product['inventory'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <form method="POST" class="inline">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                            <button type="submit" 
                                                                    class="<?= $product['inventory'] > 0 ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900' ?>">
                                                                <i class="fas fa-<?= $product['inventory'] > 0 ? 'eye-slash' : 'eye' ?>"></i>
                                                            </button>
                                                        </form>
                                                        
                                                        <a href="../product.php?id=<?= $product['id'] ?>" target="_blank" 
                                                           class="text-blue-600 hover:text-blue-900">
                                                            <i class="fas fa-external-link-alt"></i>
                                                        </a>
                                                        
                                                        <form method="POST" class="inline" 
                                                              onsubmit="return confirm('Are you sure you want to delete this product?')">
                                                            <input type="hidden" name="action" value="delete_product">
                                                            <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                            <button type="submit" class="text-red-600 hover:text-red-900">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200">
                                <div class="flex-1 flex justify-between sm:hidden">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                           class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                    <div>
                                        <p class="text-sm text-gray-700">
                                            Showing <span class="font-medium"><?= ($page - 1) * $limit + 1 ?></span> to 
                                            <span class="font-medium"><?= min($page * $limit, $totalProducts) ?></span> of 
                                            <span class="font-medium"><?= number_format($totalProducts) ?></span> results
                                        </p>
                                    </div>
                                    <div>
                                        <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                                   class="relative inline-flex items-center px-4 py-2 border text-sm font-medium
                                                          <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php endfor; ?>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
