<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Handle form submissions
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_supplier') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO suppliers (supplier_code, company_name, contact_person, email, phone, website, 
                                     address_line1, address_line2, city, state, postal_code, country_code,
                                     payment_terms, lead_time_days, minimum_order_amount, status, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['supplier_code'],
                $_POST['company_name'],
                $_POST['contact_person'],
                $_POST['email'],
                $_POST['phone'] ?? null,
                $_POST['website'] ?? null,
                $_POST['address_line1'] ?? null,
                $_POST['address_line2'] ?? null,
                $_POST['city'] ?? null,
                $_POST['state'] ?? null,
                $_POST['postal_code'] ?? null,
                $_POST['country_code'] ?? 'USA',
                $_POST['payment_terms'] ?? 'net_30',
                intval($_POST['lead_time_days'] ?? 7),
                floatval($_POST['minimum_order_amount'] ?? 0),
                $_POST['status'] ?? 'active',
                $_POST['notes'] ?? null
            ]);
            
            $success = 'Supplier added successfully!';
        } catch (PDOException $e) {
            $error = 'Error adding supplier: ' . $e->getMessage();
        }
    }
    
    if ($action === 'update_status') {
        $supplierId = intval($_POST['supplier_id']);
        $newStatus = $_POST['status'];
        
        $stmt = $pdo->prepare("UPDATE suppliers SET status = ? WHERE id = ?");
        if ($stmt->execute([$newStatus, $supplierId])) {
            $success = 'Supplier status updated successfully!';
        } else {
            $error = 'Failed to update supplier status.';
        }
    }
}

// Get filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query with filters
$whereConditions = [];
$params = [];

if ($statusFilter !== '') {
    $whereConditions[] = "s.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery !== '') {
    $whereConditions[] = "(s.company_name LIKE ? OR s.contact_person LIKE ? OR s.email LIKE ? OR s.supplier_code LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get suppliers with statistics
try {
    $suppliersQuery = "
        SELECT s.*, c.name as country_name,
               COUNT(DISTINCT sp.id) as product_count,
               COUNT(DISTINCT po.id) as purchase_order_count,
               COALESCE(SUM(po.total_amount), 0) as total_purchases
        FROM suppliers s
        LEFT JOIN countries c ON s.country_code = c.code
        LEFT JOIN supplier_products sp ON s.id = sp.supplier_id
        LEFT JOIN purchase_orders po ON s.id = po.supplier_id AND po.status != 'cancelled'
        $whereClause
        GROUP BY s.id
        ORDER BY s.company_name
    ";

    $stmt = $pdo->prepare($suppliersQuery);
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error = "Database tables are missing. Please run the database setup first.";
        $setupNeeded = true;
        $suppliers = [];
    } else {
        throw $e;
    }
}

// Get statistics
try {
    $stats = [
        'total_suppliers' => $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
        'active_suppliers' => $pdo->query("SELECT COUNT(*) FROM suppliers WHERE status = 'active'")->fetchColumn(),
        'total_products' => $pdo->query("SELECT COUNT(*) FROM supplier_products")->fetchColumn(),
        'pending_orders' => $pdo->query("SELECT COUNT(*) FROM purchase_orders WHERE status IN ('draft', 'sent', 'confirmed')")->fetchColumn()
    ];
} catch (PDOException $e) {
    $stats = [
        'total_suppliers' => 0,
        'active_suppliers' => 0,
        'total_products' => 0,
        'pending_orders' => 0
    ];
}

// Get countries for dropdown
try {
    $countries = $pdo->query("SELECT code, name FROM countries WHERE is_active = 1 ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    $countries = [
        ['code' => 'USA', 'name' => 'United States'],
        ['code' => 'CAN', 'name' => 'Canada'],
        ['code' => 'GBR', 'name' => 'United Kingdom']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - VentDepot Admin</title>
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
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Supplier Management</h1>
                            <p class="text-gray-600 mt-2">Manage suppliers, products, and purchase relationships</p>
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <a href="inventory.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                <i class="fas fa-boxes mr-2"></i>Inventory
                            </a>
                            <a href="purchase-orders.php" class="bg-purple-600 text-white px-4 py-2 rounded-md hover:bg-purple-700">
                                <i class="fas fa-file-invoice mr-2"></i>POs
                            </a>
                            <button onclick="openAddSupplierModal()" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition duration-200">
                                <i class="fas fa-plus mr-2"></i>Add Supplier
                            </button>
                        </div>
                    </div>

                    <!-- Success/Error Messages -->
                    <?php if (isset($setupNeeded) && $setupNeeded): ?>
                        <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-semibold">Database Setup Required</h4>
                                    <p>The supplier management tables are missing. Please run the database setup to continue.</p>
                                </div>
                                <a href="../setup-supplier-module.php" target="_blank" 
                                   class="bg-yellow-600 text-white px-4 py-2 rounded hover:bg-yellow-700">
                                    <i class="fas fa-database mr-2"></i>Run Setup
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
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
                                    <i class="fas fa-truck text-blue-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Suppliers</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_suppliers']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Suppliers</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['active_suppliers']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-box text-purple-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Supplier Products</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['total_products']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-file-invoice text-orange-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Pending Orders</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= number_format($stats['pending_orders']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <form method="GET" class="flex flex-wrap items-end gap-4">
                            <div class="flex-1 min-w-64">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Search Suppliers</label>
                                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" 
                                       placeholder="Search by name, contact, email, or code..."
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                <select name="status" class="border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Statuses</option>
                                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                <i class="fas fa-search mr-2"></i>Filter
                            </button>
                            
                            <a href="suppliers.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </form>
                    </div>

                    <!-- Suppliers Table -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900">Suppliers (<?= count($suppliers) ?>)</h2>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Supplier</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Location</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Products</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Orders</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Purchases</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (empty($suppliers)): ?>
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                                                No suppliers found. <a href="#" onclick="openAddSupplierModal()" class="text-blue-600 hover:text-blue-800">Add your first supplier</a>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($suppliers as $supplier): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($supplier['company_name']) ?></div>
                                                        <div class="text-sm text-gray-500">Code: <?= htmlspecialchars($supplier['supplier_code']) ?></div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($supplier['contact_person']) ?></div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($supplier['email']) ?></div>
                                                        <?php if ($supplier['phone']): ?>
                                                            <div class="text-sm text-gray-500"><?= htmlspecialchars($supplier['phone']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm text-gray-900">
                                                        <?= htmlspecialchars($supplier['city'] ?? 'N/A') ?>, <?= htmlspecialchars($supplier['state'] ?? 'N/A') ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($supplier['country_name'] ?? 'Unknown') ?></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($supplier['product_count']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= number_format($supplier['purchase_order_count']) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    $<?= number_format($supplier['total_purchases'], 2) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?php
                                                        switch($supplier['status']) {
                                                            case 'active': echo 'bg-green-100 text-green-800'; break;
                                                            case 'inactive': echo 'bg-gray-100 text-gray-800'; break;
                                                            case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                            case 'suspended': echo 'bg-red-100 text-red-800'; break;
                                                            default: echo 'bg-gray-100 text-gray-800';
                                                        }
                                                        ?>">
                                                        <?= ucfirst($supplier['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <a href="supplier-details.php?id=<?= $supplier['id'] ?>" 
                                                           class="text-blue-600 hover:text-blue-900" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="supplier-products.php?supplier_id=<?= $supplier['id'] ?>" 
                                                           class="text-green-600 hover:text-green-900" title="View Products">
                                                            <i class="fas fa-box"></i>
                                                        </a>
                                                        <button onclick="toggleStatus(<?= $supplier['id'] ?>, '<?= $supplier['status'] ?>')" 
                                                                class="text-orange-600 hover:text-orange-900" title="Change Status">
                                                            <i class="fas fa-toggle-on"></i>
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

    <!-- Add Supplier Modal -->
    <div id="addSupplierModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-screen overflow-y-auto">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Add New Supplier</h3>
                </div>

                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="add_supplier">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Basic Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4">Basic Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Supplier Code *</label>
                            <input type="text" name="supplier_code" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="e.g., TECH001">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Company Name *</label>
                            <input type="text" name="company_name" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Company Name">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Person *</label>
                            <input type="text" name="contact_person" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Contact Person Name">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email *</label>
                            <input type="email" name="email" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="contact@company.com">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                            <input type="text" name="phone"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="+1-555-0123">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Website</label>
                            <input type="url" name="website"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="https://company.com">
                        </div>

                        <!-- Address Information -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Address Information</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 1</label>
                            <input type="text" name="address_line1"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Street Address">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Address Line 2</label>
                            <input type="text" name="address_line2"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Apt, Suite, etc.">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">City</label>
                            <input type="text" name="city"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="City">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">State/Province</label>
                            <input type="text" name="state"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="State/Province">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Postal Code</label>
                            <input type="text" name="postal_code"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="Postal Code">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                            <select name="country_code" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <?php foreach ($countries as $country): ?>
                                    <option value="<?= $country['code'] ?>" <?= $country['code'] === 'USA' ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($country['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Business Terms -->
                        <div class="md:col-span-2">
                            <h4 class="text-md font-medium text-gray-900 mb-4 mt-6">Business Terms</h4>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Terms</label>
                            <select name="payment_terms" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="net_15">Net 15 Days</option>
                                <option value="net_30" selected>Net 30 Days</option>
                                <option value="net_45">Net 45 Days</option>
                                <option value="net_60">Net 60 Days</option>
                                <option value="cod">Cash on Delivery</option>
                                <option value="prepaid">Prepaid</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Lead Time (Days)</label>
                            <input type="number" name="lead_time_days" value="7" min="1" max="365"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Order Amount</label>
                            <input type="number" name="minimum_order_amount" value="0" min="0" step="0.01"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="active" selected>Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="pending">Pending</option>
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                            <textarea name="notes" rows="3"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                      placeholder="Additional notes about this supplier..."></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                        <button type="button" onclick="closeAddSupplierModal()"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Add Supplier
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddSupplierModal() {
            document.getElementById('addSupplierModal').classList.remove('hidden');
        }

        function closeAddSupplierModal() {
            document.getElementById('addSupplierModal').classList.add('hidden');
        }

        function toggleStatus(supplierId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

            if (confirm(`Are you sure you want to change this supplier's status to ${newStatus}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="supplier_id" value="${supplierId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('addSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddSupplierModal();
            }
        });
    </script>
</body>
</html>
