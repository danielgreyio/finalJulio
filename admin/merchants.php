<?php
require_once '../config/database.php';
require_once '../includes/Mailer.php';

// Require admin login
requireRole('admin');

// Handle merchant actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();

    $action     = $_POST['action'] ?? '';
    $merchantId = intval($_POST['merchant_id'] ?? 0);

    if ($merchantId > 0) {
        $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ? AND role = 'merchant'");
        $stmt->execute([$merchantId]);
        $merchant = $stmt->fetch();
    }

    if (!empty($merchant)) {
        $mailer = new Mailer();

        if ($action === 'approve') {
            $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$merchantId]);
            $mailer->sendMerchantStatusUpdate($merchant['email'], $merchant['username'], 'approved');
            $success = "Merchant approved and notified by email.";

        } elseif ($action === 'reject') {
            $reason = $_POST['rejection_reason'] ?? 'Application does not meet our requirements.';
            $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?")->execute([$merchantId]);
            $mailer->sendMerchantStatusUpdate($merchant['email'], $merchant['username'], 'rejected', $reason);
            $success = "Merchant rejected and notified by email.";

        } elseif ($action === 'suspend') {
            $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$merchantId]);
            $mailer->sendMerchantStatusUpdate($merchant['email'], $merchant['username'], 'suspended');
            $success = "Merchant suspended and notified by email.";
        }
    }
}

// Get merchants with their statistics
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$sortBy = $_GET['sort'] ?? 'created_at';

$whereConditions = ["role = 'merchant'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = 'email LIKE ?';
    $params[] = "%$search%";
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get total count
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalMerchants = $countStmt->fetchColumn();
$totalPages = ceil($totalMerchants / $perPage);

// Get merchants with their stats
$sql = "
    SELECT 
        u.*,
        COUNT(DISTINCT p.id) as product_count,
        COUNT(DISTINCT o.id) as order_count,
        COALESCE(SUM(o.total), 0) as total_revenue,
        MAX(o.created_at) as last_order_date
    FROM users u
    LEFT JOIN products p ON u.id = p.merchant_id
    LEFT JOIN orders o ON o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    $whereClause
    GROUP BY u.id
    ORDER BY u.$sortBy DESC
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$merchants = $stmt->fetchAll();

// Get merchant statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_merchants,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_this_month
    FROM users 
    WHERE role = 'merchant'
";
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute();
$merchantStats = $statsStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Merchants - VentDepot Admin</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">Verify Merchants</h1>
                            <p class="text-gray-600 mt-2">Review and manage merchant applications</p>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            Total: <?= number_format($totalMerchants) ?> merchants
                        </div>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <?= $success ?>
                        </div>
                    <?php endif; ?>

                    <!-- Merchant Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100">
                                    <i class="fas fa-store text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Total Merchants</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($merchantStats['total_merchants']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100">
                                    <i class="fas fa-user-plus text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">New This Week</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($merchantStats['new_this_week']) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100">
                                    <i class="fas fa-calendar text-yellow-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">New This Month</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($merchantStats['new_this_month']) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="Search by email...">
                            </div>
                            
                            <div>
                                <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                                <select name="sort" id="sort"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Registration Date</option>
                                    <option value="email" <?= $sortBy === 'email' ? 'selected' : '' ?>>Email</option>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-search mr-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Merchants List -->
                    <?php if (empty($merchants)): ?>
                        <div class="bg-white rounded-lg shadow-md p-12 text-center">
                            <i class="fas fa-store text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">No merchants found</h3>
                            <p class="text-gray-500">Try adjusting your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach ($merchants as $merchant): ?>
                                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                                    <!-- Merchant Header -->
                                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                            <div class="flex items-center">
                                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center mr-4">
                                                    <i class="fas fa-store text-green-600 text-xl"></i>
                                                </div>
                                                <div>
                                                    <h3 class="text-lg font-semibold text-gray-900">
                                                        <?= htmlspecialchars($merchant['email']) ?>
                                                    </h3>
                                                    <p class="text-sm text-gray-600">
                                                        Merchant ID: <?= $merchant['id'] ?> | 
                                                        Registered: <?= date('M j, Y', strtotime($merchant['created_at'])) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="mt-2 sm:mt-0">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    Active Merchant
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Merchant Stats -->
                                    <div class="px-6 py-4">
                                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                                            <div class="text-center">
                                                <p class="text-2xl font-bold text-blue-600"><?= $merchant['product_count'] ?></p>
                                                <p class="text-sm text-gray-600">Products Listed</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-2xl font-bold text-green-600"><?= $merchant['order_count'] ?></p>
                                                <p class="text-sm text-gray-600">Orders (30 days)</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-2xl font-bold text-purple-600">$<?= number_format($merchant['total_revenue'], 2) ?></p>
                                                <p class="text-sm text-gray-600">Revenue (30 days)</p>
                                            </div>
                                            <div class="text-center">
                                                <p class="text-sm font-medium text-gray-900">
                                                    <?= $merchant['last_order_date'] ? date('M j, Y', strtotime($merchant['last_order_date'])) : 'No orders' ?>
                                                </p>
                                                <p class="text-sm text-gray-600">Last Order</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Merchant Actions -->
                                    <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                            <div class="flex space-x-4 mb-2 sm:mb-0">
                                                <a href="../merchant/dashboard.php?view_as=<?= $merchant['id'] ?>" 
                                                   class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                                    <i class="fas fa-eye mr-1"></i>View Store
                                                </a>
                                                <a href="merchant-details.php?id=<?= $merchant['id'] ?>" 
                                                   class="text-green-600 hover:text-green-800 text-sm font-medium">
                                                    <i class="fas fa-info-circle mr-1"></i>View Details
                                                </a>
                                            </div>
                                            
                                            <!-- Action Buttons -->
                                            <div class="flex items-center space-x-2" x-data="{ showRejectModal: false }">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="merchant_id" value="<?= $merchant['id'] ?>">
                                                    <button type="submit" 
                                                            class="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                </form>
                                                
                                                <button @click="showRejectModal = true"
                                                        class="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700">
                                                    <i class="fas fa-times mr-1"></i>Reject
                                                </button>
                                                
                                                <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to suspend this merchant?')">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <input type="hidden" name="merchant_id" value="<?= $merchant['id'] ?>">
                                                    <button type="submit" 
                                                            class="bg-yellow-600 text-white px-3 py-1 rounded text-sm hover:bg-yellow-700">
                                                        <i class="fas fa-pause mr-1"></i>Suspend
                                                    </button>
                                                </form>
                                                
                                                <!-- Reject Modal -->
                                                <div x-show="showRejectModal" 
                                                     class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
                                                     x-transition>
                                                    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                                                        <div class="mt-3">
                                                            <h3 class="text-lg font-medium text-gray-900 mb-4">Reject Merchant</h3>
                                                            <form method="POST">
                                                                <input type="hidden" name="action" value="reject">
                                                                <input type="hidden" name="merchant_id" value="<?= $merchant['id'] ?>">
                                                                <div class="mb-4">
                                                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                                                        Rejection Reason
                                                                    </label>
                                                                    <textarea name="rejection_reason" rows="3" required
                                                                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                                                              placeholder="Please provide a reason for rejection..."></textarea>
                                                                </div>
                                                                <div class="flex justify-end space-x-3">
                                                                    <button type="button" @click="showRejectModal = false"
                                                                            class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                                                                        Cancel
                                                                    </button>
                                                                    <button type="submit"
                                                                            class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">
                                                                        Reject Merchant
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <div class="flex justify-center mt-8">
                                <nav class="flex space-x-2">
                                    <?php if ($page > 1): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            Previous
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                                           class="px-3 py-2 border rounded-md <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                                           class="px-3 py-2 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                                            Next
                                        </a>
                                    <?php endif; ?>
                                </nav>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
