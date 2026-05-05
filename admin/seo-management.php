<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_seo_settings') {
        $productId = intval($_POST['product_id']);
        $metaTitle = trim($_POST['meta_title'] ?? '');
        $metaDescription = trim($_POST['meta_description'] ?? '');
        $metaKeywords = trim($_POST['meta_keywords'] ?? '');
        $ogTitle = trim($_POST['og_title'] ?? '');
        $ogDescription = trim($_POST['og_description'] ?? '');
        $ogImage = trim($_POST['og_image'] ?? '');
        $twitterTitle = trim($_POST['twitter_title'] ?? '');
        $twitterDescription = trim($_POST['twitter_description'] ?? '');
        $twitterImage = trim($_POST['twitter_image'] ?? '');
        
        try {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET meta_title = ?, meta_description = ?, meta_keywords = ?,
                    og_title = ?, og_description = ?, og_image = ?,
                    twitter_title = ?, twitter_description = ?, twitter_image = ?
                WHERE id = ?
            ");
            
            $stmt->execute([
                $metaTitle, $metaDescription, $metaKeywords,
                $ogTitle, $ogDescription, $ogImage,
                $twitterTitle, $twitterDescription, $twitterImage,
                $productId
            ]);
            
            $success = 'SEO settings updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating SEO settings: ' . $e->getMessage();
        }
    } elseif ($action === 'bulk_update_seo') {
        $productsToUpdate = $_POST['products'] ?? [];
        $seoField = $_POST['seo_field'] ?? '';
        $seoValue = $_POST['seo_value'] ?? '';
        
        if (!empty($productsToUpdate) && !empty($seoField) && in_array($seoField, [
            'meta_title', 'meta_description', 'meta_keywords',
            'og_title', 'og_description', 'og_image',
            'twitter_title', 'twitter_description', 'twitter_image'
        ])) {
            try {
                $placeholders = str_repeat('?,', count($productsToUpdate) - 1) . '?';
                $stmt = $pdo->prepare("
                    UPDATE products 
                    SET $seoField = ?
                    WHERE id IN ($placeholders)
                ");
                
                $params = array_merge([$seoValue], $productsToUpdate);
                $stmt->execute($params);
                
                $success = 'Bulk SEO update completed successfully!';
            } catch (Exception $e) {
                $error = 'Error performing bulk SEO update: ' . $e->getMessage();
            }
        } else {
            $error = 'Invalid bulk update parameters.';
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';

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

$whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get products with pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$productsQuery = "
    SELECT p.*, u.email as merchant_email,
           CASE 
               WHEN p.meta_title IS NOT NULL AND p.meta_title != '' THEN 'Complete'
               WHEN p.meta_title IS NULL OR p.meta_title = '' THEN 'Missing'
               ELSE 'Partial'
           END as seo_status
    FROM products p
    LEFT JOIN users u ON p.merchant_id = u.id
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

// Get SEO statistics
$stats = [
    'total_products' => $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn(),
    'complete_seo' => $pdo->query("SELECT COUNT(*) FROM products WHERE meta_title IS NOT NULL AND meta_title != ''")->fetchColumn(),
    'missing_seo' => $pdo->query("SELECT COUNT(*) FROM products WHERE meta_title IS NULL OR meta_title = ''")->fetchColumn(),
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SEO Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .seo-complete {
            background-color: #dcfce7;
        }
        .seo-missing {
            background-color: #fee2e2;
        }
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
                            <h1 class="text-3xl font-bold text-gray-900">SEO Management</h1>
                            <p class="text-gray-600 mt-2">Manage SEO settings for all products</p>
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
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
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
                                    <p class="text-sm font-medium text-gray-500">Complete SEO</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['complete_seo']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $stats['total_products'] > 0 ? round(($stats['complete_seo']/$stats['total_products'])*100, 1) : 0 ?>% complete</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Missing SEO</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['missing_seo']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $stats['total_products'] > 0 ? round(($stats['missing_seo']/$stats['total_products'])*100, 1) : 0 ?>% incomplete</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-search mr-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Bulk Actions -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Bulk SEO Update</h2>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <input type="hidden" name="action" value="bulk_update_seo">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">SEO Field</label>
                                <select name="seo_field" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="meta_title">Meta Title</option>
                                    <option value="meta_description">Meta Description</option>
                                    <option value="meta_keywords">Meta Keywords</option>
                                    <option value="og_title">Open Graph Title</option>
                                    <option value="og_description">Open Graph Description</option>
                                    <option value="og_image">Open Graph Image</option>
                                    <option value="twitter_title">Twitter Title</option>
                                    <option value="twitter_description">Twitter Description</option>
                                    <option value="twitter_image">Twitter Image</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Value</label>
                                <input type="text" name="seo_value" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="Enter value to apply">
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                                    <i class="fas fa-bolt mr-2"></i>Apply to Selected
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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                            <input type="checkbox" id="select-all" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Merchant</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">SEO Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($products)): ?>
                                        <tr>
                                            <td colspan="6" class="px-6 py-4 text-center text-gray-500">
                                                No products found.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr class="<?= $product['seo_status'] === 'Complete' ? 'seo-complete' : 'seo-missing' ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <input type="checkbox" name="products[]" value="<?= $product['id'] ?>" 
                                                           class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 product-checkbox">
                                                </td>
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
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($product['merchant_email'] ?? 'N/A') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?= $product['seo_status'] === 'Complete' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                        <?= $product['seo_status'] ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <button onclick="openSeoModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')" 
                                                            class="text-blue-600 hover:text-blue-900">
                                                        <i class="fas fa-edit"></i> Edit SEO
                                                    </button>
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

    <!-- SEO Edit Modal -->
    <div id="seoModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-screen overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Edit SEO Settings</h3>
                <p id="modalProductName" class="text-sm text-gray-500 mt-1"></p>
            </div>
            <form method="POST" class="px-6 py-4">
                <input type="hidden" name="action" value="update_seo_settings">
                <input type="hidden" name="product_id" id="seoProductId">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-3">SEO Settings</h4>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Title</label>
                            <input type="text" name="meta_title" id="metaTitle" maxlength="255"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Recommended: 50-60 characters</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Description</label>
                            <textarea name="meta_description" id="metaDescription" rows="3" maxlength="160"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Recommended: 150-160 characters</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Meta Keywords</label>
                            <input type="text" name="meta_keywords" id="metaKeywords"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <p class="text-xs text-gray-500 mt-1">Separate keywords with commas</p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-3">Social Media Settings</h4>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Open Graph Title</label>
                            <input type="text" name="og_title" id="ogTitle" maxlength="255"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Open Graph Description</label>
                            <textarea name="og_description" id="ogDescription" rows="2" maxlength="300"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                            <p class="text-xs text-gray-500 mt-1">Recommended: 200-300 characters</p>
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Open Graph Image URL</label>
                            <input type="url" name="og_image" id="ogImage"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="border-t border-gray-200 pt-4 mt-4">
                            <h5 class="text-sm font-medium text-gray-900 mb-3">Twitter Card Settings</h5>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Twitter Title</label>
                                <input type="text" name="twitter_title" id="twitterTitle" maxlength="255"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Twitter Description</label>
                                <textarea name="twitter_description" id="twitterDescription" rows="2" maxlength="200"
                                          class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"></textarea>
                                <p class="text-xs text-gray-500 mt-1">Recommended: 150-200 characters</p>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Twitter Image URL</label>
                                <input type="url" name="twitter_image" id="twitterImage"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeSeoModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        Save SEO Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Select all checkboxes
        document.getElementById('select-all').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.product-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
        
        // SEO Modal Functions
        function openSeoModal(productId, productName) {
            // Set product info
            document.getElementById('seoProductId').value = productId;
            document.getElementById('modalProductName').textContent = productName;
            
            // Fetch current SEO settings for the product via AJAX
            fetch(`api/seo-api.php?action=get_product_seo&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const seo = data.data;
                        document.getElementById('metaTitle').value = seo.meta_title || productName;
                        document.getElementById('metaDescription').value = seo.meta_description || '';
                        document.getElementById('metaKeywords').value = seo.meta_keywords || '';
                        document.getElementById('ogTitle').value = seo.og_title || productName;
                        document.getElementById('ogDescription').value = seo.og_description || '';
                        document.getElementById('ogImage').value = seo.og_image || '';
                        document.getElementById('twitterTitle').value = seo.twitter_title || productName;
                        document.getElementById('twitterDescription').value = seo.twitter_description || '';
                        document.getElementById('twitterImage').value = seo.twitter_image || '';
                    } else {
                        // Clear fields if there's an error
                        document.getElementById('metaTitle').value = productName;
                        document.getElementById('metaDescription').value = '';
                        document.getElementById('metaKeywords').value = '';
                        document.getElementById('ogTitle').value = productName;
                        document.getElementById('ogDescription').value = '';
                        document.getElementById('ogImage').value = '';
                        document.getElementById('twitterTitle').value = productName;
                        document.getElementById('twitterDescription').value = '';
                        document.getElementById('twitterImage').value = '';
                    }
                })
                .catch(error => {
                    console.error('Error fetching SEO data:', error);
                    // Clear fields if there's an error
                    document.getElementById('metaTitle').value = productName;
                    document.getElementById('metaDescription').value = '';
                    document.getElementById('metaKeywords').value = '';
                    document.getElementById('ogTitle').value = productName;
                    document.getElementById('ogDescription').value = '';
                    document.getElementById('ogImage').value = '';
                    document.getElementById('twitterTitle').value = productName;
                    document.getElementById('twitterDescription').value = '';
                    document.getElementById('twitterImage').value = '';
                });
            
            // Show modal
            document.getElementById('seoModal').classList.remove('hidden');
            document.getElementById('seoModal').classList.add('flex');
        }
        
        function closeSeoModal() {
            document.getElementById('seoModal').classList.add('hidden');
            document.getElementById('seoModal').classList.remove('flex');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('seoModal');
            if (event.target === modal) {
                closeSeoModal();
            }
        }
        
        // Auto-generate SEO fields based on product name
        function autoGenerateSEO() {
            const productName = document.getElementById('modalProductName').textContent;
            const productId = document.getElementById('seoProductId').value;
            
            // Set basic SEO fields
            document.getElementById('metaTitle').value = productName;
            document.getElementById('ogTitle').value = productName;
            document.getElementById('twitterTitle').value = productName;
            
            // TODO: Add more sophisticated auto-generation logic
            // For example, fetch product description and generate meta description
        }
    </script>
</body>
</html>