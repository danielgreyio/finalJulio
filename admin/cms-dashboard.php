<?php
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Get statistics
$stats = [
    'total_banners' => $pdo->query("SELECT COUNT(*) FROM frontend_banners")->fetchColumn(),
    'active_banners' => $pdo->query("SELECT COUNT(*) FROM frontend_banners WHERE is_active = 1")->fetchColumn(),
    'total_content_blocks' => $pdo->query("SELECT COUNT(*) FROM content_blocks")->fetchColumn(),
    'total_images' => $pdo->query("SELECT COUNT(*) FROM image_assets")->fetchColumn(),
    'scheduled_posts' => $pdo->query("SELECT COUNT(*) FROM social_posts WHERE status = 'scheduled'")->fetchColumn(),
    'published_posts' => $pdo->query("SELECT COUNT(*) FROM social_posts WHERE status = 'published'")->fetchColumn()
];

// Get recent banners
$stmt = $pdo->query("SELECT * FROM frontend_banners ORDER BY created_at DESC LIMIT 5");
$recentBanners = $stmt->fetchAll();

// Get recent content blocks
$stmt = $pdo->query("SELECT cb.*, fs.name as section_name FROM content_blocks cb LEFT JOIN frontend_sections fs ON cb.section_id = fs.id ORDER BY cb.created_at DESC LIMIT 5");
$recentContent = $stmt->fetchAll();

// Get recent social posts
$stmt = $pdo->query("SELECT * FROM social_posts ORDER BY created_at DESC LIMIT 5");
$recentPosts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Dashboard - VentDepot Admin</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">CMS Dashboard</h1>
                            <p class="text-gray-600 mt-2">Manage your frontend content, banners, and social media</p>
                        </div>
                    </div>

                    <!-- Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                                    <i class="fas fa-image text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= $stats['total_banners'] ?></h3>
                                    <p class="text-gray-600">Total Banners</p>
                                </div>
                            </div>
                            <div class="mt-4 text-sm text-green-600">
                                <span><?= $stats['active_banners'] ?> active</span>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-green-100 text-green-600">
                                    <i class="fas fa-file-alt text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= $stats['total_content_blocks'] ?></h3>
                                    <p class="text-gray-600">Content Blocks</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                                    <i class="fas fa-images text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= $stats['total_images'] ?></h3>
                                    <p class="text-gray-600">Image Assets</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                                    <i class="fas fa-calendar-alt text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= $stats['scheduled_posts'] ?></h3>
                                    <p class="text-gray-600">Scheduled Posts</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-indigo-100 text-indigo-600">
                                    <i class="fas fa-share-alt text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= $stats['published_posts'] ?></h3>
                                    <p class="text-gray-600">Published Posts</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="p-3 rounded-full bg-red-100 text-red-600">
                                    <i class="fas fa-shopping-cart text-xl"></i>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <?= $pdo->query("SELECT COUNT(*) FROM product_carousels")->fetchColumn() ?>
                                    </h3>
                                    <p class="text-gray-600">Product Carousels</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <!-- Recent Banners -->
                        <div class="lg:col-span-1 bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Recent Banners</h2>
                            </div>
                            <div class="p-6">
                                <?php if (empty($recentBanners)): ?>
                                    <p class="text-gray-500 text-center py-4">No banners found</p>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($recentBanners as $banner): ?>
                                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded">
                                                <div>
                                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($banner['title']) ?></h3>
                                                    <p class="text-sm text-gray-500">
                                                        <?= ucfirst($banner['banner_type']) ?> 
                                                        <?php if ($banner['is_active']): ?>
                                                            <span class="text-green-600">• Active</span>
                                                        <?php else: ?>
                                                            <span class="text-red-600">• Inactive</span>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <a href="cms-banners.php?action=edit&id=<?= $banner['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4">
                                    <a href="cms-banners.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View all banners →
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Content -->
                        <div class="lg:col-span-1 bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Recent Content</h2>
                            </div>
                            <div class="p-6">
                                <?php if (empty($recentContent)): ?>
                                    <p class="text-gray-500 text-center py-4">No content blocks found</p>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($recentContent as $content): ?>
                                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded">
                                                <div>
                                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars($content['title']) ?></h3>
                                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($content['section_name'] ?? 'Unknown Section') ?></p>
                                                </div>
                                                <a href="cms-content.php?action=edit&id=<?= $content['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4">
                                    <a href="cms-content.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View all content →
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Social Posts -->
                        <div class="lg:col-span-1 bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Recent Social Posts</h2>
                            </div>
                            <div class="p-6">
                                <?php if (empty($recentPosts)): ?>
                                    <p class="text-gray-500 text-center py-4">No social posts found</p>
                                <?php else: ?>
                                    <div class="space-y-4">
                                        <?php foreach ($recentPosts as $post): ?>
                                            <div class="flex items-center justify-between p-3 hover:bg-gray-50 rounded">
                                                <div>
                                                    <h3 class="font-medium text-gray-900"><?= htmlspecialchars(substr($post['content'], 0, 30)) ?>...</h3>
                                                    <p class="text-sm text-gray-500">
                                                        <?= ucfirst($post['platform']) ?> • 
                                                        <span class="capitalize"><?= $post['status'] ?></span>
                                                    </p>
                                                </div>
                                                <a href="cms-social.php?action=edit&id=<?= $post['id'] ?>" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-4">
                                    <a href="cms-social.php" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        View all posts →
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mt-8 bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Quick Actions</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <a href="cms-banners.php?action=add" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <div class="p-3 bg-blue-100 text-blue-600 rounded-full mb-3">
                                        <i class="fas fa-plus-circle text-xl"></i>
                                    </div>
                                    <span class="font-medium text-gray-900">Add Banner</span>
                                </a>
                                
                                <a href="cms-content.php?action=add" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <div class="p-3 bg-green-100 text-green-600 rounded-full mb-3">
                                        <i class="fas fa-file-alt text-xl"></i>
                                    </div>
                                    <span class="font-medium text-gray-900">Add Content</span>
                                </a>
                                
                                <a href="cms-products.php" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <div class="p-3 bg-purple-100 text-purple-600 rounded-full mb-3">
                                        <i class="fas fa-shopping-cart text-xl"></i>
                                    </div>
                                    <span class="font-medium text-gray-900">Manage Carousels</span>
                                </a>
                                
                                <a href="cms-social.php?action=add" class="flex flex-col items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                                    <div class="p-3 bg-yellow-100 text-yellow-600 rounded-full mb-3">
                                        <i class="fas fa-share-alt text-xl"></i>
                                    </div>
                                    <span class="font-medium text-gray-900">Create Post</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>