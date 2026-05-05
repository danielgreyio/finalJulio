<?php
require_once '../config/database.php';

// Require merchant login
requireRole('merchant');

$productId = intval($_GET['id'] ?? 0);
$merchantId = $_SESSION['user_id'];

// Get product and verify ownership
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND merchant_id = ?");
$stmt->execute([$productId, $merchantId]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: products.php');
    exit;
}

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $category = trim($_POST['category'] ?? '');
    $stock = intval($_POST['stock'] ?? 0);
    $imageUrl = trim($_POST['image_url'] ?? '');
    
    // SEO fields
    $metaTitle = trim($_POST['meta_title'] ?? '');
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $metaKeywords = trim($_POST['meta_keywords'] ?? '');
    $ogTitle = trim($_POST['og_title'] ?? '');
    $ogDescription = trim($_POST['og_description'] ?? '');
    $ogImage = trim($_POST['og_image'] ?? '');
    $twitterTitle = trim($_POST['twitter_title'] ?? '');
    $twitterDescription = trim($_POST['twitter_description'] ?? '');
    $twitterImage = trim($_POST['twitter_image'] ?? '');
    
    // Validation
    if (empty($name) || empty($description) || $price <= 0 || $stock < 0) {
        $error = 'Please fill in all required fields with valid values.';
    } elseif (strlen($name) > 255) {
        $error = 'Product name must be less than 255 characters.';
    } elseif ($price > 999999.99) {
        $error = 'Price cannot exceed $999,999.99.';
    } else {
        // Update product
        $stmt = $pdo->prepare("
            UPDATE products 
            SET name = ?, description = ?, price = ?, category = ?, inventory = ?, image_url = ?,
                meta_title = ?, meta_description = ?, meta_keywords = ?,
                og_title = ?, og_description = ?, og_image = ?,
                twitter_title = ?, twitter_description = ?, twitter_image = ?
            WHERE id = ? AND merchant_id = ?
        ");
        
        if ($stmt->execute([$name, $description, $price, $category, $stock, $imageUrl,
                           $metaTitle, $metaDescription, $metaKeywords,
                           $ogTitle, $ogDescription, $ogImage,
                           $twitterTitle, $twitterDescription, $twitterImage,
                           $productId, $merchantId])) {
            $success = 'Product updated successfully! <a href="../product.php?id=' . $productId . '" class="text-blue-600 hover:text-blue-800">View Product</a>';
            
            // Refresh product data
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND merchant_id = ?");
            $stmt->execute([$productId, $merchantId]);
            $product = $stmt->fetch();
        } else {
            $error = 'Failed to update product. Please try again.';
        }
    }
}

// Get categories for dropdown
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$existingCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

// ... existing HTML code ...

            <!-- SEO Settings -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">SEO Settings</h2>
                <p class="text-sm text-gray-600 mb-4">Optimize your product for search engines and social media sharing.</p>
                
                <div class="space-y-6">
                    <div>
                        <label for="meta_title" class="block text-sm font-medium text-gray-700 mb-2">
                            Meta Title
                        </label>
                        <input type="text" name="meta_title" id="meta_title" maxlength="255"
                               value="<?= htmlspecialchars($product['meta_title'] ?? $product['name']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter meta title for SEO">
                        <p class="text-xs text-gray-500 mt-1">Recommended: 50-60 characters</p>
                    </div>
                    
                    <div>
                        <label for="meta_description" class="block text-sm font-medium text-gray-700 mb-2">
                            Meta Description
                        </label>
                        <textarea name="meta_description" id="meta_description" rows="3" maxlength="160"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter meta description for SEO"><?= htmlspecialchars($product['meta_description'] ?? substr($product['description'], 0, 160)) ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Recommended: 150-160 characters</p>
                    </div>
                    
                    <div>
                        <label for="meta_keywords" class="block text-sm font-medium text-gray-700 mb-2">
                            Meta Keywords
                        </label>
                        <input type="text" name="meta_keywords" id="meta_keywords"
                               value="<?= htmlspecialchars($product['meta_keywords'] ?? $product['category']) ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter keywords separated by commas">
                        <p class="text-xs text-gray-500 mt-1">Separate keywords with commas</p>
                    </div>
                </div>
            </div>

            <!-- Social Media Settings -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Social Media Settings</h2>
                <p class="text-sm text-gray-600 mb-4">Customize how your product appears when shared on social media.</p>
                
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="og_title" class="block text-sm font-medium text-gray-700 mb-2">
                                Open Graph Title
                            </label>
                            <input type="text" name="og_title" id="og_title" maxlength="255"
                                   value="<?= htmlspecialchars($product['og_title'] ?? $product['name']) ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter Open Graph title">
                        </div>
                        
                        <div>
                            <label for="og_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Open Graph Image URL
                            </label>
                            <input type="url" name="og_image" id="og_image"
                                   value="<?= htmlspecialchars($product['og_image'] ?? $product['image_url']) ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="https://example.com/og-image.jpg">
                        </div>
                    </div>
                    
                    <div>
                        <label for="og_description" class="block text-sm font-medium text-gray-700 mb-2">
                            Open Graph Description
                        </label>
                        <textarea name="og_description" id="og_description" rows="2" maxlength="300"
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Enter Open Graph description"><?= htmlspecialchars($product['og_description'] ?? substr($product['description'], 0, 300)) ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Recommended: 200-300 characters</p>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Twitter Card Settings</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="twitter_title" class="block text-sm font-medium text-gray-700 mb-2">
                                    Twitter Title
                                </label>
                                <input type="text" name="twitter_title" id="twitter_title" maxlength="255"
                                       value="<?= htmlspecialchars($product['twitter_title'] ?? $product['name']) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter Twitter card title">
                            </div>
                            
                            <div>
                                <label for="twitter_image" class="block text-sm font-medium text-gray-700 mb-2">
                                    Twitter Image URL
                                </label>
                                <input type="url" name="twitter_image" id="twitter_image"
                                       value="<?= htmlspecialchars($product['twitter_image'] ?? $product['image_url']) ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="https://example.com/twitter-image.jpg">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="twitter_description" class="block text-sm font-medium text-gray-700 mb-2">
                                Twitter Description
                            </label>
                            <textarea name="twitter_description" id="twitter_description" rows="2" maxlength="200"
                                      class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                      placeholder="Enter Twitter card description"><?= htmlspecialchars($product['twitter_description'] ?? substr($product['description'], 0, 200)) ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Recommended: 150-200 characters</p>
                        </div>
                    </div>
                </div>
            </div>

// ... existing HTML code ...