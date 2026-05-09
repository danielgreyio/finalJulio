<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    $userId = intval($_POST['user_id'] ?? 0);
    
    if ($action === 'delete' && $userId > 0) {
        // Don't allow deleting admin users
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user && $user['role'] !== 'admin') {
            $deleteStmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $deleteStmt->execute([$userId]);
            $success = 'User deleted successfully.';
        } else {
            $error = 'Cannot delete admin users.';
        }
    } elseif ($action === 'change_role' && $userId > 0) {
        $newRole = $_POST['new_role'] ?? '';
        if (in_array($newRole, ['customer', 'merchant', 'admin'])) {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$newRole, $userId]);
            $success = 'User role updated successfully.';
        }
    }
}

// Get users with pagination and filtering
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$allowedSortColumns = ['id', 'email', 'role', 'created_at', 'status'];
$sortBy = in_array($_GET['sort'] ?? '', $allowedSortColumns) ? $_GET['sort'] : 'created_at';
$sortOrder = strtoupper($_GET['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';

$whereConditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $whereConditions[] = 'email LIKE ?';
    $params[] = "%$search%";
}

if (!empty($role)) {
    $whereConditions[] = 'role = ?';
    $params[] = $role;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
$orderClause = "ORDER BY $sortBy $sortOrder";

// Get total count
$countSql = "SELECT COUNT(*) FROM users $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalUsers = $countStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// Get users
$sql = "SELECT * FROM users $whereClause $orderClause LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$statsQuery = "
    SELECT
        role,
        COUNT(*) as count,
        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
    FROM users
    GROUP BY role
";
$statsStmt = $pdo->prepare($statsQuery);
$statsStmt->execute();
$userStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array for easier access
$stats = [];
foreach ($userStats as $stat) {
    $stats[$stat['role']] = [
        'count' => $stat['count'],
        'new_this_week' => $stat['new_this_week']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - VentDepot Admin</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">Manage Users</h1>
                            <p class="text-gray-600 mt-2">View and manage all platform users</p>
                        </div>
                        
                        <div class="text-sm text-gray-600">
                            Total: <?= number_format($totalUsers) ?> users
                        </div>
                    </div>

                    <?php if (isset($success)): ?>
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <!-- User Statistics -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100">
                                    <i class="fas fa-users text-blue-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Customers</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['customer']['count'] ?? 0) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100">
                                    <i class="fas fa-store text-green-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Merchants</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['merchant']['count'] ?? 0) ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-red-100">
                                    <i class="fas fa-user-shield text-red-600 text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-600">Admins</p>
                                    <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['admin']['count'] ?? 0) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                <input type="text" name="search" id="search" value="<?= htmlspecialchars($search) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="Search by email...">
                            </div>
                            
                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                                <select name="role" id="role"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="">All Roles</option>
                                    <option value="customer" <?= $role === 'customer' ? 'selected' : '' ?>>Customer</option>
                                    <option value="merchant" <?= $role === 'merchant' ? 'selected' : '' ?>>Merchant</option>
                                    <option value="admin" <?= $role === 'admin' ? 'selected' : '' ?>>Admin</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                                <select name="sort" id="sort"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                                    <option value="created_at" <?= $sortBy === 'created_at' ? 'selected' : '' ?>>Registration Date</option>
                                    <option value="email" <?= $sortBy === 'email' ? 'selected' : '' ?>>Email</option>
                                    <option value="role" <?= $sortBy === 'role' ? 'selected' : '' ?>>Role</option>
                                </select>
                            </div>
                            
                            <div class="flex items-end">
                                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded-md hover:bg-blue-700">
                                    <i class="fas fa-search mr-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Users Table -->
                    <?php if (empty($users)): ?>
                        <div class="bg-white rounded-lg shadow-md p-12 text-center">
                            <i class="fas fa-users text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-xl font-semibold text-gray-600 mb-2">No users found</h3>
                            <p class="text-gray-500">Try adjusting your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <!-- Desktop Table -->
                            <div class="hidden md:block">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($users as $user): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="flex items-center">
                                                        <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center">
                                                            <i class="fas fa-user text-gray-600"></i>
                                                        </div>
                                                        <div class="ml-4">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?= htmlspecialchars($user['email']) ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                ID: <?= $user['id'] ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?php
                                                        switch($user['role']) {
                                                            case 'admin': echo 'bg-red-100 text-red-800'; break;
                                                            case 'merchant': echo 'bg-green-100 text-green-800'; break;
                                                            case 'customer': echo 'bg-blue-100 text-blue-800'; break;
                                                            default: echo 'bg-gray-100 text-gray-800';
                                                        }
                                                        ?>">
                                                        <?= ucfirst($user['role']) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                    <?= date('M j, Y', strtotime($user['created_at'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex space-x-2">
                                                        <?php if ($user['role'] !== 'admin'): ?>
                                                            <!-- Role Change Dropdown -->
                                                            <form method="POST" class="inline">
                                                                <input type="hidden" name="action" value="change_role">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <select name="new_role" onchange="this.form.submit()"
                                                                        class="text-xs border border-gray-300 rounded px-2 py-1">
                                                                    <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                                                    <option value="merchant" <?= $user['role'] === 'merchant' ? 'selected' : '' ?>>Merchant</option>
                                                                    <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                                </select>
                                                            </form>
                                                            
                                                            <!-- Delete Button -->
                                                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                                <button type="submit" class="text-red-600 hover:text-red-900" title="Delete">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 text-xs">Protected</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Mobile Cards -->
                            <div class="md:hidden space-y-4 p-4">
                                <?php foreach ($users as $user): ?>
                                    <div class="border border-gray-200 rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-3">
                                            <div class="flex items-center">
                                                <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                                    <i class="fas fa-user text-gray-600"></i>
                                                </div>
                                                <div>
                                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($user['email']) ?></p>
                                                    <p class="text-sm text-gray-500">ID: <?= $user['id'] ?></p>
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                <?php
                                                switch($user['role']) {
                                                    case 'admin': echo 'bg-red-100 text-red-800'; break;
                                                    case 'merchant': echo 'bg-green-100 text-green-800'; break;
                                                    case 'customer': echo 'bg-blue-100 text-blue-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                                ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm text-gray-600">Registered: <?= date('M j, Y', strtotime($user['created_at'])) ?></span>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <div class="flex space-x-2">
                                                    <form method="POST" class="inline">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <select name="new_role" onchange="this.form.submit()"
                                                                class="text-xs border border-gray-300 rounded px-2 py-1">
                                                            <option value="customer" <?= $user['role'] === 'customer' ? 'selected' : '' ?>>Customer</option>
                                                            <option value="merchant" <?= $user['role'] === 'merchant' ? 'selected' : '' ?>>Merchant</option>
                                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        </select>
                                                    </form>
                                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure?')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="text-red-600">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
