<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'adjust_inventory') {
        try {
            $productId = intval($_POST['product_id']);
            $locationId = intval($_POST['location_id']);
            $adjustmentType = $_POST['adjustment_type'];
            $quantity = intval($_POST['quantity']);
            $reason = $_POST['reason'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            $pdo->beginTransaction();
            
            // Get current inventory
            $stmt = $pdo->prepare("SELECT * FROM product_inventory WHERE product_id = ? AND location_id = ?");
            $stmt->execute([$productId, $locationId]);
            $currentInventory = $stmt->fetch();
            
            if (!$currentInventory) {
                // Create new inventory record
                $stmt = $pdo->prepare("
                    INSERT INTO product_inventory (product_id, location_id, quantity_on_hand, quantity_reserved)
                    VALUES (?, ?, 0, 0)
                ");
                $stmt->execute([$productId, $locationId]);
                $currentInventory = ['quantity_on_hand' => 0];
            }
            
            // Calculate new quantity
            $newQuantity = $currentInventory['quantity_on_hand'];
            $movementQuantity = 0;
            
            if ($adjustmentType === 'add') {
                $newQuantity += $quantity;
                $movementQuantity = $quantity;
                $movementType = 'in';
            } else {
                $newQuantity -= $quantity;
                $movementQuantity = -$quantity;
                $movementType = 'out';
            }
            
            if ($newQuantity < 0) {
                throw new Exception('Insufficient inventory for this adjustment');
            }
            
            // Update inventory
            $stmt = $pdo->prepare("
                UPDATE product_inventory 
                SET quantity_on_hand = ?, last_movement_at = NOW()
                WHERE product_id = ? AND location_id = ?
            ");
            $stmt->execute([$newQuantity, $productId, $locationId]);
            
            // Record movement
            $stmt = $pdo->prepare("
                INSERT INTO inventory_movements (product_id, location_id, movement_type, quantity, reference_type, reason, notes, created_by)
                VALUES (?, ?, ?, ?, 'adjustment', ?, ?, ?)
            ");
            $stmt->execute([$productId, $locationId, $movementType, $movementQuantity, $reason, $notes, $_SESSION['user_id']]);
            
            // Update product stock (sum across all locations)
            $stmt = $pdo->prepare("
                UPDATE products 
                SET stock = (SELECT COALESCE(SUM(quantity_on_hand), 0) FROM product_inventory WHERE product_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$productId, $productId]);
            
            $pdo->commit();
            $success = 'Inventory adjusted successfully!';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Error adjusting inventory: ' . $e->getMessage();
        }
    }
}

// Get filters
$locationFilter = $_GET['location'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$lowStockFilter = $_GET['low_stock'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$whereConditions = ['1=1'];
$params = [];

if ($locationFilter !== '') {
    $whereConditions[] = "il.id = ?";
    $params[] = $locationFilter;
}

if ($categoryFilter !== '') {
    $whereConditions[] = "p.category = ?";
    $params[] = $categoryFilter;
}

if ($lowStockFilter === '1') {
    $whereConditions[] = "pi.quantity_on_hand <= pi.reorder_point";
}

if ($searchQuery !== '') {
    $whereConditions[] = "(p.name LIKE ? OR p.category LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get inventory data
$inventoryQuery = "
    SELECT p.id as product_id, p.name as product_name, p.category, p.price,
           pi.*, il.location_name, il.location_code,
           u.email as merchant_email,
           CASE 
               WHEN pi.quantity_on_hand <= pi.reorder_point THEN 'low'
               WHEN pi.quantity_on_hand <= (pi.reorder_point * 1.5) THEN 'medium'
               ELSE 'good'
           END as stock_status
    FROM product_inventory pi
    JOIN products p ON pi.product_id = p.id
    JOIN inventory_locations il ON pi.location_id = il.id
    LEFT JOIN users u ON p.merchant_id = u.id
    $whereClause
    ORDER BY il.location_name, p.name
";

$stmt = $pdo->prepare($inventoryQuery);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Get statistics
$stats = [
    'total_products' => $pdo->query("SELECT COUNT(DISTINCT product_id) FROM product_inventory")->fetchColumn(),
    'total_locations' => $pdo->query("SELECT COUNT(*) FROM inventory_locations WHERE status = 'active'")->fetchColumn(),
    'low_stock_items' => $pdo->query("SELECT COUNT(*) FROM product_inventory WHERE quantity_on_hand <= reorder_point")->fetchColumn(),
    'total_value' => $pdo->query("
        SELECT COALESCE(SUM(pi.quantity_on_hand * p.price), 0)
        FROM product_inventory pi
        JOIN products p ON pi.product_id = p.id
    ")->fetchColumn()
];

// Get locations and categories for filters
$locations = $pdo->query("SELECT id, location_name FROM inventory_locations WHERE status = 'active' ORDER BY location_name")->fetchAll();
$categories = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category")->fetchAll();
$products = $pdo->query("SELECT id, name FROM products ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="js/realtime-inventory.js"></script>
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
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Inventory Management</h1>
                            <p class="text-gray-600 mt-2">Monitor stock levels, track movements, and manage inventory</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="suppliers.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-truck mr-2"></i>Suppliers
                            </a>
                            <a href="purchase-orders.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                                <i class="fas fa-file-invoice mr-2"></i>POs
                            </a>
                            <a href="inventory-locations.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-warehouse mr-2"></i>Locations
                            </a>
                            <a href="inventory-receiving.php" class="bg-orange-600 text-white px-4 py-2 rounded-md hover:bg-orange-700">
                                <i class="fas fa-truck-loading mr-2"></i>Receive
                            </a>
                            <button onclick="openAdjustmentModal()" class="bg-gray-800 text-white px-4 py-2 rounded-md hover:bg-gray-900">
                                <i class="fas fa-plus-minus mr-2"></i>Adjust
                            </button>
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
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

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-box text-blue-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Products in Inventory</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_products']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-warehouse text-green-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Locations</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_locations']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Low Stock Items</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['low_stock_items']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-dollar-sign text-purple-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Inventory Value</p>
                                    <p class="text-2xl font-semibold text-gray-900">$<?= number_format($stats['total_value'], 2) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <form method="GET" class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-64">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Search Products</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                                       placeholder="Search by product name or category..."
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                                <select name="location" class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?= $location['id'] ?>" <?= $locationFilter == $location['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($location['location_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                                <select name="category" class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['category'] ?>" <?= $categoryFilter === $category['category'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($category['category']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Stock Level</label>
                                <select name="low_stock" class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Stock Levels</option>
                                    <option value="1" <?= $lowStockFilter === '1' ? 'selected' : '' ?>>Low Stock Only</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            
                            <a href="inventory.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </form>
                    </div>

                    <!-- Inventory Table -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900">Inventory Levels (<?= count($inventory) ?>)</h2>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">On Hand</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reserved</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Available</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reorder Point</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Value</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($inventory)): ?>
                                        <tr>
                                            <td colspan="9" class="px-6 py-4 text-center text-gray-500">
                                                No inventory records found. <a href="#" onclick="openAdjustmentModal()" class="text-blue-600 hover:text-blue-800">Add inventory</a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($inventory as $item): ?>
                                            <tr class="hover:bg-gray-50" data-product-id="<?= $item['product_id'] ?>" data-location-id="<?= $item['location_id'] ?>">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['product_name']) ?></div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($item['category'] ?? 'Uncategorized') ?></div>
                                                        <?php if ($item['merchant_email']): ?>
                                                            <div class="text-xs text-gray-400">Merchant: <?= htmlspecialchars($item['merchant_email']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($item['location_name']) ?></div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($item['location_code']) ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 quantity-cell" id="quantity-<?= $item['product_id'] ?>-<?= $item['location_id'] ?>">
                                                    <?= number_format($item['quantity_on_hand']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($item['quantity_reserved']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($item['quantity_available']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($item['reorder_point']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    $<?= number_format($item['quantity_on_hand'] * $item['price'], 2) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?php
                                                        switch($item['stock_status']) {
                                                            case 'low': echo 'bg-red-100 text-red-800'; break;
                                                            case 'medium': echo 'bg-yellow-100 text-yellow-800'; break;
                                                            case 'good': echo 'bg-green-100 text-green-800'; break;
                                                            default: echo 'bg-gray-100 text-gray-800';
                                                        }
                                                        ?>">
                                                        <?php
                                                        switch($item['stock_status']) {
                                                            case 'low': echo 'Low Stock'; break;
                                                            case 'medium': echo 'Medium'; break;
                                                            case 'good': echo 'Good'; break;
                                                            default: echo 'Unknown';
                                                        }
                                                        ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <button onclick="adjustInventory(<?= $item['product_id'] ?>, <?= $item['location_id'] ?>, '<?= htmlspecialchars($item['product_name']) ?>', '<?= htmlspecialchars($item['location_name']) ?>')"
                                                                class="text-blue-600 hover:text-blue-900" title="Adjust Inventory">
                                                            <i class="fas fa-plus-minus"></i>
                                                        </button>
                                                        <a href="inventory-movements.php?product_id=<?= $item['product_id'] ?>&location_id=<?= $item['location_id'] ?>"
                                                           class="text-green-600 hover:text-green-900" title="View Movements">
                                                            <i class="fas fa-history"></i>
                                                        </a>
                                                        <button onclick="viewDetails(<?= $item['product_id'] ?>, <?= $item['location_id'] ?>)"
                                                                class="text-purple-600 hover:text-purple-900" title="View Details">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Inventory Adjustment Modal -->
    <div id="adjustmentModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Adjust Inventory</h3>
                </div>

                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="adjust_inventory">
                    <input type="hidden" name="product_id" id="adjust_product_id">
                    <input type="hidden" name="location_id" id="adjust_location_id">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                            <select name="product_id_select" id="product_select" onchange="updateProductId()"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                            <select name="location_id_select" id="location_select" onchange="updateLocationId()"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Select Location</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?= $location['id'] ?>"><?= htmlspecialchars($location['location_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Adjustment Type</label>
                            <select name="adjustment_type" required
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="add">Add Stock</option>
                                <option value="remove">Remove Stock</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" required min="1"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Enter quantity">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                            <select name="reason"
                                    class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="manual_adjustment">Manual Adjustment</option>
                                <option value="stock_count">Physical Stock Count</option>
                                <option value="damage">Damaged Goods</option>
                                <option value="theft">Theft/Loss</option>
                                <option value="return">Customer Return</option>
                                <option value="supplier_return">Return to Supplier</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Additional notes about this adjustment..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeAdjustmentModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Adjust Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Initialize real-time inventory updates
        document.addEventListener('DOMContentLoaded', function() {
            // Set up real-time inventory updates
            realTimeInventory.onUpdate(function(data) {
                console.log('Inventory update received:', data);
                // Update the inventory table with new data
                updateInventoryRow(data.data);
                
                // Show notification
                showNotification(`Inventory updated for ${data.data.product_name}`, 'success');
            });
            
            realTimeInventory.onSummary(function(data) {
                console.log('Inventory summary received:', data);
                // Update summary statistics
                updateInventorySummary(data.data);
            });
            
            realTimeInventory.onUpdate(function(data) {
                if (data.type === 'connected') {
                    showNotification('Connected to real-time inventory updates', 'info');
                } else if (data.type === 'error') {
                    showNotification('Connection to inventory updates lost', 'error');
                }
            });
        });
        
        function openAdjustmentModal() {
            document.getElementById('adjustmentModal').classList.remove('hidden');
        }

        function closeAdjustmentModal() {
            document.getElementById('adjustmentModal').classList.add('hidden');
            // Reset form
            document.getElementById('adjust_product_id').value = '';
            document.getElementById('adjust_location_id').value = '';
            document.getElementById('product_select').value = '';
            document.getElementById('location_select').value = '';
        }

        function adjustInventory(productId, locationId, productName, locationName) {
            document.getElementById('adjust_product_id').value = productId;
            document.getElementById('adjust_location_id').value = locationId;
            document.getElementById('product_select').value = productId;
            document.getElementById('location_select').value = locationId;
            openAdjustmentModal();
        }

        function updateProductId() {
            const select = document.getElementById('product_select');
            document.getElementById('adjust_product_id').value = select.value;
        }

        function updateLocationId() {
            const select = document.getElementById('location_select');
            document.getElementById('adjust_location_id').value = select.value;
        }

        function viewDetails(productId, locationId) {
            window.open(`inventory-details.php?product_id=${productId}&location_id=${locationId}`, '_blank');
        }
        
        // Update inventory row with new data
        function updateInventoryRow(data) {
            // Find the row for this product and location
            const row = document.querySelector(`tr[data-product-id="${data.product_id}"][data-location-id="${data.location_id}"]`);
            if (row) {
                // Update quantity
                const quantityCell = row.querySelector('.quantity-cell');
                if (quantityCell) {
                    quantityCell.textContent = data.quantity;
                    
                    // Update stock status class
                    let stockStatus = 'good';
                    if (data.quantity <= 5) {
                        stockStatus = 'low';
                    } else if (data.quantity <= 10) {
                        stockStatus = 'medium';
                    }
                    
                    quantityCell.className = `quantity-cell px-6 py-4 whitespace-nowrap text-sm font-medium ${
                        stockStatus === 'low' ? 'text-red-600' : 
                        stockStatus === 'medium' ? 'text-yellow-600' : 'text-green-600'
                    }`;
                }
                
                // Add highlight effect
                row.classList.add('bg-yellow-100');
                setTimeout(() => {
                    row.classList.remove('bg-yellow-100');
                }, 2000);
            }
        }
        
        // Update inventory summary
        function updateInventorySummary(data) {
            if (data.total_products !== undefined) {
                document.querySelector('#totalProducts').textContent = data.total_products;
            }
            if (data.total_items !== undefined) {
                document.querySelector('#totalItems').textContent = data.total_items;
            }
            if (data.low_stock_items !== undefined) {
                document.querySelector('#lowStockItems').textContent = data.low_stock_items;
            }
        }
        
        // Show notification
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            notification.textContent = message;
            
            // Add to document
            document.body.appendChild(notification);
            
            // Remove after 3 seconds
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        // Close modal when clicking outside
        document.getElementById('adjustmentModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAdjustmentModal();
            }
        });
    </script>
</body>
</html>
