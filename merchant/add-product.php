<?php
require_once '../config/database.php';

// Require merchant login
requireRole('merchant');

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $unitOfMeasure = trim($_POST['unit_of_measure'] ?? 'pieza');
    $category = trim($_POST['custom_category'] ?? $_POST['category'] ?? '');
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
        // Insert product
        $stmt = $pdo->prepare("
            INSERT INTO products (merchant_id, name, description, price, unit_of_measure, category, stock, image_url,
                                meta_title, meta_description, meta_keywords,
                                og_title, og_description, og_image,
                                twitter_title, twitter_description, twitter_image)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([$_SESSION['user_id'], $name, $description, $price, $unitOfMeasure, $category, $stock, $imageUrl,
                           $metaTitle, $metaDescription, $metaKeywords,
                           $ogTitle, $ogDescription, $ogImage,
                           $twitterTitle, $twitterDescription, $twitterImage])) {
            $productId = $pdo->lastInsertId();

            // Insert shipping dimensions
            require_once '../classes/ShippingCalculator.php';
            $shippingCalc = new ShippingCalculator($pdo);

            $dimensions = [
                'weight_kg' => floatval($_POST['weight'] ?? 0.5),
                'length_cm' => floatval($_POST['length'] ?? 20),
                'width_cm' => floatval($_POST['width'] ?? 15),
                'height_cm' => floatval($_POST['height'] ?? 10),
                'fragile' => isset($_POST['fragile']),
                'hazardous' => isset($_POST['hazardous']),
                'requires_signature' => isset($_POST['requires_signature'])
            ];

            $shippingCalc->updateProductDimensions($productId, $dimensions);

            // Save bulk pricing tiers
            $tierQtys   = $_POST['tier_qty']   ?? [];
            $tierPrices = $_POST['tier_price']  ?? [];
            foreach ($tierQtys as $i => $qty) {
                $qty   = intval($qty);
                $tprice = floatval($tierPrices[$i] ?? 0);
                if ($qty > 0 && $tprice > 0) {
                    $tierStmt = $pdo->prepare("
                        INSERT INTO product_pricing_tiers (product_id, min_quantity, price_per_unit)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE price_per_unit = VALUES(price_per_unit)
                    ");
                    $tierStmt->execute([$productId, $qty, $tprice]);
                }
            }

            $success = 'Product added successfully! <a href="../product.php?id=' . $productId . '" class="text-blue-600 hover:text-blue-800">View Product</a>';

            // Clear form data
            $_POST = [];
        } else {
            $error = 'Failed to add product. Please try again.';
        }
    }
}

// Get categories for dropdown
$stmt = $pdo->prepare("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category");
$stmt->execute();
$existingCategories = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Product - VentDepot Merchant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="../index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                    <span class="text-gray-400">|</span>
                    <a href="dashboard.php" class="text-lg font-semibold text-gray-700 hover:text-blue-600">Merchant Dashboard</a>
                </div>
                
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-600 hover:text-blue-600">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Add New Product</h1>
            <p class="text-gray-600 mt-2">Fill in the details below to list your product on VentDepot.</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $success ?>
            </div>
        <?php endif; ?>

        <!-- Product Form -->
        <form method="POST" class="space-y-8" x-data="{ 
            category: '<?= htmlspecialchars($_POST['category'] ?? '') ?>',
            customCategory: false,
            previewImage: '<?= htmlspecialchars($_POST['image_url'] ?? '') ?>'
        }">
            <!-- Basic Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Basic Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Product Name *
                        </label>
                        <input type="text" name="name" id="name" required maxlength="255"
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter product name">
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                            Product Description *
                        </label>
                        <textarea name="description" id="description" rows="4" required
                                  class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                  placeholder="Describe your product in detail..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            Price (USD) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-2 text-gray-500">$</span>
                            <input type="number" name="price" id="price" step="0.01" min="0.01" max="999999.99" required
                                   value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md pl-8 pr-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="0.00">
                        </div>
                    </div>
                    
                    <!-- Unit of Measure -->
                    <div>
                        <label for="unit_of_measure" class="block text-sm font-medium text-gray-700 mb-2">
                            Unit of Measure *
                        </label>
                        <select name="unit_of_measure" id="unit_of_measure"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <?php
                            $units = ['pieza','bolsa 25kg','metro lineal','m²','m³','rollo','kg','tonelada','galón','litro','pallet'];
                            $selectedUnit = $_POST['unit_of_measure'] ?? 'pieza';
                            foreach ($units as $u):
                            ?>
                            <option value="<?= htmlspecialchars($u) ?>" <?= $u === $selectedUnit ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">How this product is sold (e.g. per bag, per meter)</p>
                    </div>

                    <!-- Bulk Pricing Tiers -->
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Bulk Pricing Tiers <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <div id="pricing-tiers" class="space-y-2 mb-3">
                            <div class="grid grid-cols-5 gap-2 text-xs text-gray-500 font-medium px-1">
                                <span class="col-span-2">Min. Quantity</span>
                                <span class="col-span-2">Price per Unit ($)</span>
                                <span></span>
                            </div>
                        </div>
                        <button type="button" id="add-tier-btn"
                                class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1">
                            <i class="fas fa-plus-circle"></i> Add tier
                        </button>
                        <p class="text-xs text-gray-500 mt-1">E.g. 1–9 pcs at base price, 10–49 at $X, 50+ at $Y</p>
                    </div>

                    <div>
                        <label for="stock" class="block text-sm font-medium text-gray-700 mb-2">
                            Stock Quantity *
                        </label>
                        <input type="number" name="stock" id="stock" min="0" required
                               value="<?= htmlspecialchars($_POST['stock'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="0">
                    </div>

                    <!-- Shipping Dimensions Section -->
                    <div class="border-t border-gray-200 pt-6 mt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-shipping-fast mr-2 text-blue-600"></i>
                            Shipping Information
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Accurate dimensions help calculate shipping costs and ensure proper packaging.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                            <div>
                                <label for="weight" class="block text-sm font-medium text-gray-700 mb-2">Weight (kg) *</label>
                                <input type="number" name="weight" id="weight" step="0.001" min="0" required
                                       value="<?= htmlspecialchars($_POST['weight'] ?? '0.5') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="0.500">
                                <p class="text-xs text-gray-500 mt-1">Actual weight in kilograms</p>
                            </div>

                            <div>
                                <label for="length" class="block text-sm font-medium text-gray-700 mb-2">Length (cm) *</label>
                                <input type="number" name="length" id="length" step="0.1" min="0" required
                                       value="<?= htmlspecialchars($_POST['length'] ?? '20') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="20.0">
                                <p class="text-xs text-gray-500 mt-1">Longest side</p>
                            </div>

                            <div>
                                <label for="width" class="block text-sm font-medium text-gray-700 mb-2">Width (cm) *</label>
                                <input type="number" name="width" id="width" step="0.1" min="0" required
                                       value="<?= htmlspecialchars($_POST['width'] ?? '15') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="15.0">
                                <p class="text-xs text-gray-500 mt-1">Middle side</p>
                            </div>

                            <div>
                                <label for="height" class="block text-sm font-medium text-gray-700 mb-2">Height (cm) *</label>
                                <input type="number" name="height" id="height" step="0.1" min="0" required
                                       value="<?= htmlspecialchars($_POST['height'] ?? '10') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                       placeholder="10.0">
                                <p class="text-xs text-gray-500 mt-1">Shortest side</p>
                            </div>
                        </div>

                        <div class="mt-6">
                            <h4 class="text-sm font-medium text-gray-700 mb-3">Special Handling Requirements</h4>
                            <div class="space-y-3">
                                <div class="flex items-center">
                                    <input type="checkbox" name="fragile" id="fragile"
                                           <?= isset($_POST['fragile']) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="fragile" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>
                                        Fragile item (requires special handling - additional $5.00 fee)
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" name="hazardous" id="hazardous"
                                           <?= isset($_POST['hazardous']) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="hazardous" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-radiation text-red-500 mr-1"></i>
                                        Hazardous material (additional $15.00 fee and restrictions apply)
                                    </label>
                                </div>

                                <div class="flex items-center">
                                    <input type="checkbox" name="requires_signature" id="requires_signature"
                                           <?= isset($_POST['requires_signature']) ? 'checked' : '' ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <label for="requires_signature" class="ml-2 text-sm text-gray-700">
                                        <i class="fas fa-signature text-blue-500 mr-1"></i>
                                        Requires signature on delivery (recommended for high-value items)
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Category</h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Select Category
                        </label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="category_type" value="existing" 
                                       x-model="customCategory" :value="false"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Choose from existing categories</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="category_type" value="custom" 
                                       x-model="customCategory" :value="true"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                <span class="ml-2 text-sm text-gray-700">Create new category</span>
                            </label>
                        </div>
                    </div>
                    
                    <div x-show="!customCategory">
                        <input type="hidden" name="category" x-bind:value="customCategory ? '' : category">
                        <select x-model="category"
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Select a category</option>
                            <?php foreach ($existingCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div x-show="customCategory">
                        <input type="text" name="custom_category" x-model="category"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Enter new category name">
                    </div>
                </div>
            </div>

            <!-- Product Images -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Product Images</h2>
                
                <div class="space-y-4">
                    <div>
                        <label for="image_url" class="block text-sm font-medium text-gray-700 mb-2">
                            Image URL
                        </label>
                        <input type="url" name="image_url" id="image_url" x-model="previewImage"
                               value="<?= htmlspecialchars($_POST['image_url'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="https://example.com/image.jpg">
                        <p class="text-sm text-gray-500 mt-1">
                            Enter a direct URL to your product image. For best results, use high-quality images (at least 800x600px).
                        </p>
                    </div>
                    
                    <!-- Image Preview -->
                    <div x-show="previewImage" class="mt-4">
                        <p class="text-sm font-medium text-gray-700 mb-2">Image Preview:</p>
                        <img :src="previewImage" alt="Product preview" 
                             class="w-48 h-48 object-cover rounded-lg border border-gray-300"
                             @error="previewImage = ''">
                    </div>
                    
                    <!-- Drag & Drop Upload Area (Mock) -->
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                        <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                        <p class="text-lg font-medium text-gray-600 mb-2">Drag & Drop Images Here</p>
                        <p class="text-sm text-gray-500 mb-4">Or click to browse files</p>
                        <button type="button" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                            Choose Files
                        </button>
                        <p class="text-xs text-gray-400 mt-2">
                            Supported formats: JPG, PNG, GIF (Max 5MB per image)
                        </p>
                    </div>
                </div>
            </div>

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
                               value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>"
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
                                  placeholder="Enter meta description for SEO"><?= htmlspecialchars($_POST['meta_description'] ?? '') ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Recommended: 150-160 characters</p>
                    </div>
                    
                    <div>
                        <label for="meta_keywords" class="block text-sm font-medium text-gray-700 mb-2">
                            Meta Keywords
                        </label>
                        <input type="text" name="meta_keywords" id="meta_keywords"
                               value="<?= htmlspecialchars($_POST['meta_keywords'] ?? '') ?>"
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
                                   value="<?= htmlspecialchars($_POST['og_title'] ?? '') ?>"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="Enter Open Graph title">
                        </div>
                        
                        <div>
                            <label for="og_image" class="block text-sm font-medium text-gray-700 mb-2">
                                Open Graph Image URL
                            </label>
                            <input type="url" name="og_image" id="og_image"
                                   value="<?= htmlspecialchars($_POST['og_image'] ?? '') ?>"
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
                                  placeholder="Enter Open Graph description"><?= htmlspecialchars($_POST['og_description'] ?? '') ?></textarea>
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
                                       value="<?= htmlspecialchars($_POST['twitter_title'] ?? '') ?>"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                       placeholder="Enter Twitter card title">
                            </div>
                            
                            <div>
                                <label for="twitter_image" class="block text-sm font-medium text-gray-700 mb-2">
                                    Twitter Image URL
                                </label>
                                <input type="url" name="twitter_image" id="twitter_image"
                                       value="<?= htmlspecialchars($_POST['twitter_image'] ?? '') ?>"
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
                                      placeholder="Enter Twitter card description"><?= htmlspecialchars($_POST['twitter_description'] ?? '') ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Recommended: 150-200 characters</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-between items-center">
                <a href="dashboard.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-arrow-left mr-2"></i>Cancel
                </a>
                
                <div class="space-x-4">
                    <button type="button" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                        Save as Draft
                    </button>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Product
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Handle category selection
        document.addEventListener('alpine:init', () => {
            Alpine.data('productForm', () => ({
                category: '<?= htmlspecialchars($_POST['category'] ?? '') ?>',
                customCategory: false,
                previewImage: '<?= htmlspecialchars($_POST['image_url'] ?? '') ?>'
            }));
        });
        
        // Auto-generate SKU based on product name
        document.getElementById('name').addEventListener('input', function() {
            const name = this.value;
            const sku = name.toUpperCase().replace(/[^A-Z0-9]/g, '').substring(0, 10);
            document.getElementById('sku').value = sku;
        });

        // Bulk pricing tiers
        document.getElementById('add-tier-btn').addEventListener('click', function() {
            const container = document.getElementById('pricing-tiers');
            const row = document.createElement('div');
            row.className = 'grid grid-cols-5 gap-2 items-center';
            row.innerHTML = `
                <input type="number" name="tier_qty[]" min="1" placeholder="Min qty"
                       class="col-span-2 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                <input type="number" name="tier_price[]" min="0" step="0.01" placeholder="Price"
                       class="col-span-2 border border-gray-300 rounded-md px-3 py-1.5 text-sm focus:ring-2 focus:ring-blue-500">
                <button type="button" class="text-red-400 hover:text-red-600 text-center"
                        onclick="this.closest('.grid').remove()">
                    <i class="fas fa-times"></i>
                </button>`;
            container.appendChild(row);
        });
    </script>
</body>
</html>
