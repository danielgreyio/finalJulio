<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
requireRole('admin');

$currentPage = 'cms-banners.php'; // For sidebar highlighting

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$bannerId = intval($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    if ($action === 'add' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $subtitle = trim($_POST['subtitle'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $buttonText = trim($_POST['button_text'] ?? '');
        $buttonUrl = trim($_POST['button_url'] ?? '');
        $target = trim($_POST['target'] ?? '_self');
        $bannerType = trim($_POST['banner_type'] ?? 'carousel');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $startDate = trim($_POST['start_date'] ?? '');
        $endDate = trim($_POST['end_date'] ?? '');
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        $imageId = intval($_POST['image_id'] ?? 0);
        
        // Validate required fields
        if (empty($title)) {
            $error = 'Title is required.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO frontend_banners 
                        (title, subtitle, content, button_text, button_url, target, banner_type, is_active, start_date, end_date, sort_order, image_id)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $title, $subtitle, $content, $buttonText, $buttonUrl, $target, $bannerType, 
                        $isActive, $startDate ?: null, $endDate ?: null, $sortOrder, $imageId ?: null
                    ]);
                    $success = 'Banner added successfully!';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE frontend_banners 
                        SET title = ?, subtitle = ?, content = ?, button_text = ?, button_url = ?, 
                            target = ?, banner_type = ?, is_active = ?, start_date = ?, end_date = ?, 
                            sort_order = ?, image_id = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $title, $subtitle, $content, $buttonText, $buttonUrl, $target, $bannerType, 
                        $isActive, $startDate ?: null, $endDate ?: null, $sortOrder, $imageId ?: null, $bannerId
                    ]);
                    $success = 'Banner updated successfully!';
                }
            } catch (Exception $e) {
                $error = 'Error saving banner: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM frontend_banners WHERE id = ?");
            $stmt->execute([$bannerId]);
            $success = 'Banner deleted successfully!';
        } catch (Exception $e) {
            $error = 'Error deleting banner: ' . $e->getMessage();
        }
    }
}

// Get banner for edit
$banner = null;
if ($action === 'edit' && $bannerId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM frontend_banners WHERE id = ?");
        $stmt->execute([$bannerId]);
        $banner = $stmt->fetch();
        
        if (!$banner) {
            $error = 'Banner not found.';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error fetching banner: ' . $e->getMessage(); 
        $action = 'list';
    }
}

// Get all banners for listing
$banners = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT * FROM frontend_banners ORDER BY sort_order, created_at DESC");
        $banners = $stmt->fetchAll();
    } catch (Exception $e) {
        // If table doesn't exist, handle gracefully or log error
        // $error = 'Database error: ' . $e->getMessage();
    }
}

// Get images for dropdown
try {
    $stmt = $pdo->query("SELECT id, filename, title FROM image_assets WHERE is_active = 1 ORDER BY created_at DESC");
    $images = $stmt->fetchAll();
} catch (Exception $e) {
    $images = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banner Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden" x-data="{ sidebarOpen: false }">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Sidebar Backdrop -->
    <div class="relative z-0 flex-1 flex flex-col overflow-hidden">
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
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">
                                <?php if ($action === 'add'): ?>
                                    Add New Banner
                                <?php elseif ($action === 'edit'): ?>
                                    Edit Banner
                                <?php else: ?>
                                    Banner Management
                                <?php endif; ?>
                            </h1>
                            <p class="text-gray-600 mt-2">
                                <?php if ($action === 'add'): ?>
                                    Create a new banner for your frontend
                                <?php elseif ($action === 'edit'): ?>
                                    Update banner details
                                <?php else: ?>
                                    Manage all frontend banners
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <?php if ($action === 'list'): ?>
                                <a href="cms-banners.php?action=add" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 shadow-sm text-sm font-medium flex items-center">
                                    <i class="fas fa-plus mr-2"></i>Add Banner
                                </a>
                            <?php endif; ?>
                            <a href="cms-dashboard.php" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 shadow-sm text-sm font-medium flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i>Back to CMS
                            </a>
                        </div>
                    </div>

                    <?php if ($success): ?>
                        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Success</p>
                            <p><?= htmlspecialchars($success) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                            <p class="font-bold">Error</p>
                            <p><?= htmlspecialchars($error) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'list'): ?>
                        <!-- Banner List -->
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">All Banners</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banner</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dates</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($banners)): ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                                    No banners found. <a href="cms-banners.php?action=add" class="text-blue-600 hover:text-blue-800">Add your first banner</a>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($banners as $b): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded-md flex items-center justify-center">
                                                                <i class="fas fa-image text-gray-500"></i>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($b['title']) ?></div>
                                                                <?php if ($b['subtitle']): ?>
                                                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($b['subtitle']) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <?= ucfirst($b['banner_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($b['is_active']): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                Inactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php if ($b['start_date'] || $b['end_date']): ?>
                                                            <?php if ($b['start_date']): ?>
                                                                <div>Start: <?= date('M j, Y', strtotime($b['start_date'])) ?></div>
                                                            <?php endif; ?>
                                                            <?php if ($b['end_date']): ?>
                                                                <div>End: <?= date('M j, Y', strtotime($b['end_date'])) ?></div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="text-gray-400">No dates</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="cms-banners.php?action=edit&id=<?= $b['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="cms-banners.php?action=delete&id=<?= $b['id'] ?>" 
                                                           onclick="return confirm('Are you sure you want to delete this banner?')" 
                                                           class="text-red-600 hover:text-red-900">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Add/Edit Form -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">
                                    <?php if ($action === 'add'): ?>
                                        Banner Details
                                    <?php else: ?>
                                        Edit Banner
                                    <?php endif; ?>
                                </h2>
                            </div>
                            <form method="POST" class="p-6">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="action" value="<?= $action ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="id" value="<?= $banner['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($banner['title'] ?? '') ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Subtitle</label>
                                        <input type="text" name="subtitle" value="<?= htmlspecialchars($banner['subtitle'] ?? '') ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                                        <textarea name="content" rows="3" 
                                                  class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?= htmlspecialchars($banner['content'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Button Text</label>
                                        <input type="text" name="button_text" value="<?= htmlspecialchars($banner['button_text'] ?? '') ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Button URL</label>
                                        <input type="url" name="button_url" value="<?= htmlspecialchars($banner['button_url'] ?? '') ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Target</label>
                                        <select name="target" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="_self" <?= (isset($banner['target']) && $banner['target'] === '_self') ? 'selected' : '' ?>>Same Window</option>
                                            <option value="_blank" <?= (isset($banner['target']) && $banner['target'] === '_blank') ? 'selected' : '' ?>>New Window</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Banner Type</label>
                                        <select name="banner_type" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="hero" <?= (isset($banner['banner_type']) && $banner['banner_type'] === 'hero') ? 'selected' : '' ?>>Hero</option>
                                            <option value="carousel" <?= (isset($banner['banner_type']) && $banner['banner_type'] === 'carousel') ? 'selected' : '' ?>>Carousel</option>
                                            <option value="promotion" <?= (isset($banner['banner_type']) && $banner['banner_type'] === 'promotion') ? 'selected' : '' ?>>Promotion</option>
                                            <option value="popup" <?= (isset($banner['banner_type']) && $banner['banner_type'] === 'popup') ? 'selected' : '' ?>>Popup</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                        <input type="number" name="sort_order" value="<?= $banner['sort_order'] ?? 0 ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                                        <select name="image_id" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Select an image</option>
                                            <?php foreach ($images as $image): ?>
                                                <option value="<?= $image['id'] ?>" <?= (isset($banner['image_id']) && $banner['image_id'] == $image['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($image['title'] ?? $image['filename']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                                        <input type="date" name="start_date" value="<?= $banner['start_date'] ?? '' ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                                        <input type="date" name="end_date" value="<?= $banner['end_date'] ?? '' ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_active" id="is_active" <?= (isset($banner['is_active']) && $banner['is_active']) ? 'checked' : '' ?> 
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end space-x-3">
                                    <a href="cms-banners.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </a>
                                    <button type="submit" class="bg-blue-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <?php if ($action === 'add'): ?>
                                            Add Banner
                                        <?php else: ?>
                                            Update Banner
                                        <?php endif; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>