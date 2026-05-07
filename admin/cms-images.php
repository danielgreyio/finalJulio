<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$imageId = intval($_GET['id'] ?? 0);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    
    if ($action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $altText = trim($_POST['alt_text'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE image_assets 
                SET title = ?, alt_text = ?, caption = ?, tags = ?, is_active = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            // Convert tags to JSON array
            $tagsArray = array_filter(array_map('trim', explode(',', $tags)));
            $tagsJson = json_encode($tagsArray);
            
            $stmt->execute([$title, $altText, $caption, $tagsJson, $isActive, $imageId]);
            $success = 'Image updated successfully!';
        } catch (Exception $e) {
            $error = 'Error updating image: ' . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        try {
            // First get the image file path to delete the file
            $stmt = $pdo->prepare("SELECT file_path FROM image_assets WHERE id = ?");
            $stmt->execute([$imageId]);
            $image = $stmt->fetch();
            
            if ($image) {
                // Delete the file from filesystem
                $filePath = '../' . $image['file_path'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Delete from database
                $stmt = $pdo->prepare("DELETE FROM image_assets WHERE id = ?");
                $stmt->execute([$imageId]);
                $success = 'Image deleted successfully!';
            } else {
                $error = 'Image not found.';
            }
        } catch (Exception $e) {
            $error = 'Error deleting image: ' . $e->getMessage();
        }
    } elseif ($action === 'upload') {
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image_file'];
            
            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            $validationErrors = Security::validateFileUpload($file, $allowedTypes, $maxSize);
            
            if (empty($validationErrors)) {
                try {
                    // Derive extension from detected MIME type, not user-supplied filename
                    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
                    $detectedMime = mime_content_type($file['tmp_name']);
                    $extension = $mimeToExt[$detectedMime] ?? null;
                    if (!$extension) {
                        throw new \Exception('Invalid image type detected.');
                    }
                    $filename = uniqid() . '_' . time() . '.' . $extension;
                    $uploadDir = 'uploads/images/';
                    $uploadPath = '../' . $uploadDir;
                    
                    // Create directory if it doesn't exist
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    $fullPath = $uploadPath . $filename;
                    $relativePath = $uploadDir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
                        // Get file info
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mimeType = finfo_file($finfo, $fullPath);
                        finfo_close($finfo);
                        
                        $fileSize = filesize($fullPath);
                        
                        // Insert into database
                        $stmt = $pdo->prepare("
                            INSERT INTO image_assets 
                            (filename, original_name, file_path, file_size, mime_type, uploaded_by, is_active)
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([
                            $filename, 
                            $file['name'], 
                            $relativePath, 
                            $fileSize, 
                            $mimeType, 
                            $_SESSION['user_id']
                        ]);
                        
                        $success = 'Image uploaded successfully!';
                    } else {
                        $error = 'Error moving uploaded file.';
                    }
                } catch (Exception $e) {
                    $error = 'Error uploading image: ' . $e->getMessage();
                }
            } else {
                $error = implode(', ', $validationErrors);
            }
        } else {
            $error = 'Please select an image file to upload.';
        }
    }
}

// Get image for edit
$image = null;
if ($action === 'edit' && $imageId) {
    $stmt = $pdo->prepare("SELECT * FROM image_assets WHERE id = ?");
    $stmt->execute([$imageId]);
    $image = $stmt->fetch();
    
    if (!$image) {
        $error = 'Image not found.';
        $action = 'list';
    }
}

// Get all images for listing
$images = [];
if ($action === 'list') {
    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 24;
    $offset = ($page - 1) * $limit;
    
    $stmt = $pdo->prepare("
        SELECT *, 
               (SELECT COUNT(*) FROM frontend_banners WHERE image_id = image_assets.id) as used_in_banners,
               (SELECT COUNT(*) FROM carousel_items WHERE image_id = image_assets.id) as used_in_carousel,
               (SELECT COUNT(*) FROM social_posts WHERE image_id = image_assets.id) as used_in_posts
        FROM image_assets 
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    $images = $stmt->fetchAll();
    
    // Get total count for pagination
    $totalCount = $pdo->query("SELECT COUNT(*) FROM image_assets")->fetchColumn();
    $totalPages = ceil($totalCount / $limit);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Management - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <?php include 'header.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">
                    <?php if ($action === 'edit'): ?>
                        Edit Image
                    <?php else: ?>
                        Image Management
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 mt-2">
                    <?php if ($action === 'edit'): ?>
                        Update image details and metadata
                    <?php else: ?>
                        Manage all image assets
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex space-x-3">
                <?php if ($action === 'list'): ?>
                    <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-upload mr-2"></i>Upload Image
                    </button>
                <?php endif; ?>
                <a href="cms-dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                    <i class="fas fa-arrow-left mr-2"></i>Back to CMS
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

        <?php if ($action === 'list'): ?>
            <!-- Upload Modal -->
            <div id="upload-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
                <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
                    <div class="mt-3">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Upload Image</h3>
                            <button onclick="document.getElementById('upload-modal').classList.add('hidden')" 
                                    class="text-gray-400 hover:text-gray-500">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form method="POST" enctype="multipart/form-data" class="space-y-4">
                            <?= Security::getCSRFInput() ?>
                            <input type="hidden" name="action" value="upload">
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Select Image</label>
                                <input type="file" name="image_file" accept="image/*" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-sm text-gray-500 mt-1">JPG, PNG, GIF, or WebP. Max 5MB.</p>
                            </div>
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" 
                                        onclick="document.getElementById('upload-modal').classList.add('hidden')"
                                        class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                                    Cancel
                                </button>
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                    Upload
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Image Grid -->
            <?php if (empty($images)): ?>
                <div class="bg-white rounded-lg shadow p-8 text-center">
                    <i class="fas fa-image text-gray-300 text-5xl mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No images found</h3>
                    <p class="text-gray-500 mb-4">Get started by uploading your first image.</p>
                    <button onclick="document.getElementById('upload-modal').classList.remove('hidden')" 
                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-upload mr-2"></i>Upload Image
                    </button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <?php foreach ($images as $img): ?>
                        <div class="bg-white rounded-lg shadow overflow-hidden">
                            <div class="aspect-square overflow-hidden">
                                <img src="<?= htmlspecialchars($img['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($img['alt_text'] ?? $img['filename']) ?>"
                                     class="w-full h-full object-cover">
                            </div>
                            <div class="p-3">
                                <div class="text-sm font-medium text-gray-900 truncate">
                                    <?= htmlspecialchars($img['title'] ?? $img['original_name']) ?>
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    <?= formatBytes($img['file_size']) ?>
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <?php if ($img['is_active']): ?>
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Active</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Inactive</span>
                                    <?php endif; ?>
                                    <div class="flex space-x-1">
                                        <a href="cms-images.php?action=edit&id=<?= $img['id'] ?>" 
                                           class="text-blue-600 hover:text-blue-800">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="cms-images.php?action=delete&id=<?= $img['id'] ?>" 
                                           onclick="return confirm('Are you sure you want to delete this image?')" 
                                           class="text-red-600 hover:text-red-800">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="mt-8 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="cms-images.php?page=<?= $page - 1 ?>" 
                                   class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <a href="cms-images.php?page=<?= $i ?>" 
                                   class="px-3 py-2 rounded-md text-sm font-medium <?= $i == $page ? 'bg-blue-600 text-white' : 'bg-white border border-gray-300 text-gray-500 hover:bg-gray-50' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="cms-images.php?page=<?= $page + 1 ?>" 
                                   class="px-3 py-2 rounded-md bg-white border border-gray-300 text-sm font-medium text-gray-500 hover:bg-gray-50">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else: ?>
            <!-- Edit Form -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Edit Image</h2>
                </div>
                <form method="POST" class="p-6">
                    <?= Security::getCSRFInput() ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $image['id'] ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="mb-4">
                                <img src="<?= htmlspecialchars($image['file_path']) ?>" 
                                     alt="<?= htmlspecialchars($image['alt_text'] ?? $image['filename']) ?>"
                                     class="w-full max-w-md h-auto rounded border">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Filename</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded text-sm text-gray-600">
                                        <?= htmlspecialchars($image['original_name']) ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Size</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded text-sm text-gray-600">
                                        <?= formatBytes($image['file_size']) ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded text-sm text-gray-600">
                                        <?= htmlspecialchars($image['mime_type']) ?>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Uploaded</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded text-sm text-gray-600">
                                        <?= date('M j, Y', strtotime($image['created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($image['title'] ?? '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Alt Text</label>
                                <input type="text" name="alt_text" value="<?= htmlspecialchars($image['alt_text'] ?? '') ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Caption</label>
                                <textarea name="caption" rows="2" 
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($image['caption'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Tags</label>
                                <input type="text" name="tags" value="<?= htmlspecialchars(implode(', ', json_decode($image['tags'] ?? '[]', true))) ?>" 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <p class="text-sm text-gray-500 mt-1">Separate tags with commas</p>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" <?= $image['is_active'] ? 'checked' : '' ?> 
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end space-x-3">
                        <a href="cms-images.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Update Image
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'footer.php'; ?>
    
    <?php
    function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    ?>
</body>
</html>