<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
requireRole('admin');

$currentPage = 'cms-content.php'; // For sidebar highlighting

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$contentId = intval($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    if ($action === 'add' || $action === 'edit') {
        $sectionId = intval($_POST['section_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $contentType = trim($_POST['content_type'] ?? 'html');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $sortOrder = intval($_POST['sort_order'] ?? 0);
        
        // Validate required fields
        if (empty($title) || empty($sectionId)) {
            $error = 'Title and section are required.';
        } else {
            try {
                if ($action === 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO content_blocks 
                        (section_id, title, content, content_type, is_active, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $sectionId, $title, $content, $contentType, $isActive, $sortOrder
                    ]);
                    $success = 'Content block added successfully!';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE content_blocks 
                        SET section_id = ?, title = ?, content = ?, content_type = ?, 
                            is_active = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $sectionId, $title, $content, $contentType, $isActive, $sortOrder, $contentId
                    ]);
                    $success = 'Content block updated successfully!';
                }
            } catch (Exception $e) {
                $error = 'Error saving content block: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        try {
            $stmt = $pdo->prepare("DELETE FROM content_blocks WHERE id = ?");
            $stmt->execute([$contentId]);
            $success = 'Content block deleted successfully!';
        } catch (Exception $e) {
            $error = 'Error deleting content block: ' . $e->getMessage();
        }
    }
}

// Get content block for edit
$contentBlock = null;
if ($action === 'edit' && $contentId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM content_blocks WHERE id = ?");
        $stmt->execute([$contentId]);
        $contentBlock = $stmt->fetch();
        
        if (!$contentBlock) {
            $error = 'Content block not found.';
            $action = 'list';
        }
    } catch (Exception $e) {
        $error = 'Error fetching content block: ' . $e->getMessage();
        $action = 'list';
    }
}

// Get all content blocks for listing
$contentBlocks = [];
if ($action === 'list') {
    try {
        $stmt = $pdo->query("
            SELECT cb.*, fs.name as section_name 
            FROM content_blocks cb 
            LEFT JOIN frontend_sections fs ON cb.section_id = fs.id 
            ORDER BY cb.sort_order, cb.created_at DESC
        ");
        $contentBlocks = $stmt->fetchAll();
    } catch (Exception $e) {
        // Handle error gracefully
    }
}

// Get sections for dropdown
try {
    $stmt = $pdo->query("SELECT id, name FROM frontend_sections WHERE is_active = 1 ORDER BY sort_order, name");
    $sections = $stmt->fetchAll();
} catch (Exception $e) {
    $sections = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - VentDepot Admin</title>
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
                                    Add New Content Block
                                <?php elseif ($action === 'edit'): ?>
                                    Edit Content Block
                                <?php else: ?>
                                    Content Management
                                <?php endif; ?>
                            </h1>
                            <p class="text-gray-600 mt-2">
                                <?php if ($action === 'add'): ?>
                                    Create a new content block for your frontend
                                <?php elseif ($action === 'edit'): ?>
                                    Update content block details
                                <?php else: ?>
                                    Manage all frontend content blocks
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <?php if ($action === 'list'): ?>
                                <a href="cms-content.php?action=add" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 shadow-sm text-sm font-medium flex items-center">
                                    <i class="fas fa-plus mr-2"></i>Add Content
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
                        <!-- Content List -->
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-medium text-gray-900">All Content Blocks</h2>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Content</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Section</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($contentBlocks)): ?>
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                                    No content blocks found. <a href="cms-content.php?action=add" class="text-blue-600 hover:text-blue-800">Add your first content block</a>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($contentBlocks as $cb): ?>
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($cb['title']) ?></div>
                                                        <div class="text-sm text-gray-500">
                                                            <?= htmlspecialchars(substr(strip_tags($cb['content']), 0, 100)) ?>...
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?= htmlspecialchars($cb['section_name'] ?? 'Unknown') ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            <?= ucfirst($cb['content_type']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($cb['is_active']): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                                Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                Inactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="cms-content.php?action=edit&id=<?= $cb['id'] ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                            <i class="fas fa-edit"></i> Edit
                                                        </a>
                                                        <a href="cms-content.php?action=delete&id=<?= $cb['id'] ?>" 
                                                           onclick="return confirm('Are you sure you want to delete this content block?')" 
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
                                        Content Block Details
                                    <?php else: ?>
                                        Edit Content Block
                                    <?php endif; ?>
                                </h2>
                            </div>
                            <form method="POST" class="p-6">
                                <?= Security::getCSRFInput() ?>
                                <input type="hidden" name="action" value="<?= $action ?>">
                                <?php if ($action === 'edit'): ?>
                                    <input type="hidden" name="id" value="<?= $contentBlock['id'] ?>">
                                <?php endif; ?>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($contentBlock['title'] ?? '') ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Section *</label>
                                        <select name="section_id" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="">Select a section</option>
                                            <?php foreach ($sections as $section): ?>
                                                <option value="<?= $section['id'] ?>" <?= (isset($contentBlock['section_id']) && $contentBlock['section_id'] == $section['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($section['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Content</label>
                                        <textarea name="content" rows="6" 
                                                  class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"><?= htmlspecialchars($contentBlock['content'] ?? '') ?></textarea>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Content Type</label>
                                        <select name="content_type" class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                            <option value="text" <?= (isset($contentBlock['content_type']) && $contentBlock['content_type'] === 'text') ? 'selected' : '' ?>>Plain Text</option>
                                            <option value="html" <?= (isset($contentBlock['content_type']) && $contentBlock['content_type'] === 'html') ? 'selected' : '' ?>>HTML</option>
                                            <option value="markdown" <?= (isset($contentBlock['content_type']) && $contentBlock['content_type'] === 'markdown') ? 'selected' : '' ?>>Markdown</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                                        <input type="number" name="sort_order" value="<?= $contentBlock['sort_order'] ?? 0 ?>" 
                                               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                    </div>
                                    
                                    <div class="md:col-span-2">
                                        <div class="flex items-center">
                                            <input type="checkbox" name="is_active" id="is_active" <?= (isset($contentBlock['is_active']) && $contentBlock['is_active']) ? 'checked' : '' ?> 
                                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-6 flex justify-end space-x-3">
                                    <a href="cms-content.php" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        Cancel
                                    </a>
                                    <button type="submit" class="bg-blue-600 border border-transparent rounded-md shadow-sm py-2 px-4 inline-flex justify-center text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                        <?php if ($action === 'add'): ?>
                                            Add Content Block
                                        <?php else: ?>
                                            Update Content Block
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