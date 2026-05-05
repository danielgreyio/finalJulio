<?php
require_once 'config/database.php';
require_once 'includes/ProductReviews.php';
require_once 'includes/dummy_data.php';

// Initialize reviews system
$reviewsSystem = new ProductReviews($pdo);

// Get product ID from URL
$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch product from dummy data
$product = getDummyProduct($productId);

if (!$product) {
    header('Location: index.php');
    exit;
}

// Handle review submission
$reviewError = '';
$reviewSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    requireCSRF();
    requireLogin();
    
    $rules = [
        'rating' => ['required' => true, 'type' => 'integer', 'min_value' => 1, 'max_value' => 5],
        'title' => ['required' => true, 'max_length' => 255],
        'review_text' => ['required' => true, 'max_length' => 2000]
    ];
    
    $reviewData = Security::sanitizeArray($_POST, [
        'rating' => 'int',
        'title' => 'string',
        'review_text' => 'string'
    ]);
    
    $errors = Security::validateInput($reviewData, $rules);
    
    if (empty($errors)) {
        try {
            $canReview = $reviewsSystem->canUserReviewProduct($_SESSION['user_id'], $productId);
            
            if ($canReview['can_review']) {
                $reviewId = $reviewsSystem->addReview(
                    $productId,
                    $_SESSION['user_id'],
                    $reviewData['rating'],
                    $reviewData['title'],
                    $reviewData['review_text'],
                    $canReview['order_id'] ?? null
                );
                
                if ($reviewId) {
                    $reviewSuccess = 'Your review has been submitted successfully!';
                    // Refresh product data to show updated rating
                    $stmt = $pdo->prepare("SELECT p.*, u.email as merchant_email FROM products p JOIN users u ON p.merchant_id = u.id WHERE p.id = ?");
                    $stmt->execute([$productId]);
                    $product = $stmt->fetch();
                }
            } else {
                $reviewError = match($canReview['reason']) {
                    'already_reviewed' => 'You have already reviewed this product.',
                    'no_purchase' => 'You can only review products you have purchased.',
                    default => 'Unable to submit review at this time.'
                };
            }
        } catch (Exception $e) {
            $reviewError = $e->getMessage();
        }
    } else {
        $reviewError = implode(' ', $errors);
    }
}

// Get product reviews
$page = max(1, intval($_GET['review_page'] ?? 1));
$sortBy = $_GET['sort'] ?? 'newest';
$reviewsData = $reviewsSystem->getProductReviews($productId, $page, 5, $sortBy);
$ratingSummary = $reviewsSystem->getProductRatingSummary($productId);

// Check if user can review
$canUserReview = false;
$userReviewStatus = null;
if (isLoggedIn()) {
    $userReviewStatus = $reviewsSystem->canUserReviewProduct($_SESSION['user_id'], $productId);
    $canUserReview = $userReviewStatus['can_review'];
}

// SEO Meta Tags
$metaTitle = !empty($product['meta_title']) ? $product['meta_title'] : $product['name'] . ' - VentDepot';
$metaDescription = !empty($product['meta_description']) ? $product['meta_description'] : substr($product['description'], 0, 160);
$metaKeywords = !empty($product['meta_keywords']) ? $product['meta_keywords'] : $product['category'] . ', ' . $product['name'];

// Open Graph Tags
$ogTitle = !empty($product['og_title']) ? $product['og_title'] : $product['name'];
$ogDescription = !empty($product['og_description']) ? $product['og_description'] : substr($product['description'], 0, 300);
$ogImage = !empty($product['og_image']) ? $product['og_image'] : (!empty($product['image_url']) ? $product['image_url'] : 'https://ventdepot.com/images/default-product.jpg');
$ogUrl = 'https://ventdepot.com/product.php?id=' . $product['id'];

// Twitter Card Tags
$twitterTitle = !empty($product['twitter_title']) ? $product['twitter_title'] : $product['name'];
$twitterDescription = !empty($product['twitter_description']) ? $product['twitter_description'] : substr($product['description'], 0, 200);
$twitterImage = !empty($product['twitter_image']) ? $product['twitter_image'] : (!empty($product['image_url']) ? $product['image_url'] : 'https://ventdepot.com/images/default-product.jpg');

// Related products
// Related products (Dummy)
$allDummyForRelated = getDummyProducts();
$relatedProducts = array_filter($allDummyForRelated, function($p) use ($productId) {
    return $p['id'] != $productId;
});
$relatedProducts = array_slice($relatedProducts, 0, 4);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($metaTitle) ?></title>
    
    <!-- SEO Meta Tags -->
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($ogUrl) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
    <meta property="og:site_name" content="VentDepot">
    
    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($twitterTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($twitterDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($twitterImage) ?>">
    
    <!-- Schema.org for Google -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org/",
        "@type": "Product",
        "name": "<?= htmlspecialchars($product['name']) ?>",
        "image": "<?= htmlspecialchars($product['image_url']) ?>",
        "description": "<?= htmlspecialchars(substr($product['description'], 0, 300)) ?>",
        "sku": "<?= $product['id'] ?>",
        "offers": {
            "@type": "Offer",
            "url": "<?= htmlspecialchars($ogUrl) ?>",
            "priceCurrency": "USD",
            "price": "<?= $product['price'] ?>",
            "availability": "<?= $product['quantity'] > 0 ? 'InStock' : 'OutOfStock' ?>",
            "priceValidUntil": "<?= date('Y-m-d', strtotime('+1 year')) ?>"
        }
        <?php if ($ratingSummary['average_rating'] > 0): ?>
        ,"aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "<?= $ratingSummary['average_rating'] ?>",
            "bestRating": "5",
            "worstRating": "1",
            "ratingCount": "<?= $ratingSummary['total_reviews'] ?>"
        }
        <?php endif; ?>
    }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .product-image-main {
            aspect-ratio: 1/1;
            object-fit: cover;
        }
        .product-image-thumb {
            aspect-ratio: 1/1;
            object-fit: cover;
            cursor: pointer;
        }
        .zoom-container {
            position: relative;
            overflow: hidden;
        }
        .zoom-lens {
            position: absolute;
            border: 2px solid #000;
            background-color: rgba(255, 255, 255, 0.3);
            cursor: crosshair;
            display: none;
        }
        .zoom-result {
            position: absolute;
            border: 1px solid #d4d4d4;
            width: 300px;
            height: 300px;
            display: none;
            z-index: 100;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'includes/navigation.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-8">
        <!-- Breadcrumbs -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="index.php" class="hover:text-blue-600">Home</a></li>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li><a href="#" class="hover:text-blue-600"><?= htmlspecialchars($product['category'] ?? 'Category') ?></a></li>
                <li><i class="fas fa-chevron-right text-xs"></i></li>
                <li class="text-gray-900 truncate max-w-xs"><?= htmlspecialchars($product['name']) ?></li>
            </ol>
        </nav>

        <!-- Product Gallery and Info -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Product Gallery -->
            <div class="bg-white rounded-lg shadow-md p-4">
                <div class="flex flex-col-reverse md:flex-row gap-4">
                    <!-- Thumbnails -->
                    <div class="flex md:flex-col gap-2 md:gap-3 overflow-x-auto md:overflow-y-auto md:h-96 hide-scrollbar">
                        <img src="<?= htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/100x100') ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>" 
                             class="product-image-thumb w-16 h-16 md:w-24 md:h-24 rounded border-2 border-blue-500 flex-shrink-0">
                        <?php for ($i = 0; $i < 3; $i++): ?>
                            <img src="https://via.placeholder.com/100x100?text=Image+<?= $i+2 ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?> - <?= $i+2 ?>" 
                                 class="product-image-thumb w-16 h-16 md:w-24 md:h-24 rounded border border-gray-200 flex-shrink-0">
                        <?php endfor; ?>
                    </div>
                    
                    <!-- Main Image -->
                    <div class="flex-1">
                        <div class="zoom-container relative">
                            <img id="mainImage" 
                                 src="<?= htmlspecialchars($product['image_url'] ?? 'https://via.placeholder.com/500x500') ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-image-main w-full rounded-lg">
                            <div id="zoomLens" class="zoom-lens"></div>
                            <div id="zoomResult" class="zoom-result"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Product Info -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-3xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($product['name']) ?></h1>
                
                <!-- Rating -->
                <?php if ($ratingSummary['average_rating'] > 0): ?>
                    <div class="flex items-center mb-4">
                        <div class="flex">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?= $i <= round($ratingSummary['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="ml-2 text-sm text-gray-600">
                            <?= number_format($ratingSummary['average_rating'], 1) ?> 
                            (<?= $ratingSummary['total_reviews'] ?> reviews)
                        </span>
                    </div>
                <?php endif; ?>
                
                <!-- Price -->
                <div class="mb-6">
                    <?php if (!empty($product['compare_price']) && $product['compare_price'] > $product['price']): ?>
                        <div class="flex items-baseline">
                            <span class="text-3xl font-bold text-gray-900">$<?= number_format($product['price'], 2) ?></span>
                            <span class="ml-2 text-xl text-gray-500 line-through">$<?= number_format($product['compare_price'], 2) ?></span>
                            <span class="ml-2 bg-red-100 text-red-800 text-sm font-medium px-2.5 py-0.5 rounded">
                                Save <?= round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100) ?>%
                            </span>
                        </div>
                    <?php else: ?>
                        <span class="text-3xl font-bold text-gray-900">$<?= number_format($product['price'], 2) ?></span>
                    <?php endif; ?>
                </div>
                
                <!-- Stock Status -->
                <div class="mb-6">
                    <?php if ($product['quantity'] > 10): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <i class="fas fa-check-circle mr-1"></i> In Stock (<?= $product['quantity'] ?> available)
                        </span>
                    <?php elseif ($product['quantity'] > 0): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                            <i class="fas fa-exclamation-circle mr-1"></span> Low Stock (<?= $product['quantity'] ?> left)
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            <i class="fas fa-times-circle mr-1"></i> Out of Stock
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Short Description -->
                <?php if (!empty($product['short_description'])): ?>
                    <div class="mb-6">
                        <p class="text-gray-700"><?= htmlspecialchars($product['short_description']) ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Quantity Selector -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                    <div class="flex items-center">
                        <button id="decreaseQty" class="w-10 h-10 flex items-center justify-center border border-gray-300 bg-gray-100 rounded-l">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" id="quantity" name="quantity" min="1" max="<?= $product['quantity'] ?>" value="1" 
                               class="w-16 h-10 border-y border-gray-300 text-center">
                        <button id="increaseQty" class="w-10 h-10 flex items-center justify-center border border-gray-300 bg-gray-100 rounded-r">
                            <i class="fas fa-plus"></i>
                        </button>
                        <?php if ($product['quantity'] < 10 && $product['quantity'] > 0): ?>
                            <span class="ml-3 text-sm text-yellow-600">
                                Only <?= $product['quantity'] ?> left!
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3 mb-6">
                    <?php if ($product['quantity'] > 0): ?>
                        <button id="addToCartBtn" 
                                class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-lg transition duration-200 flex items-center justify-center"
                                data-product-id="<?= $product['id'] ?>">
                            <i class="fas fa-shopping-cart mr-2"></i> Add to Cart
                        </button>
                        <button id="buyNowBtn" 
                                class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-medium py-3 rounded-lg transition duration-200 flex items-center justify-center">
                            <i class="fas fa-bolt mr-2"></i> Buy Now
                        </button>
                    <?php else: ?>
                        <button disabled class="flex-1 bg-gray-400 text-white font-medium py-3 rounded-lg cursor-not-allowed">
                            <i class="fas fa-times-circle mr-2"></i> Out of Stock
                        </button>
                    <?php endif; ?>
                </div>
                
                <!-- Wishlist and Share -->
                <div class="flex items-center justify-between border-t border-b border-gray-200 py-4">
                    <button class="flex items-center text-gray-600 hover:text-blue-600">
                        <i class="far fa-heart mr-2"></i> Add to Wishlist
                    </button>
                    <div class="flex items-center">
                        <span class="text-gray-600 mr-2">Share:</span>
                        <div class="flex space-x-2">
                            <a href="#" class="text-gray-600 hover:text-blue-600">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="text-gray-600 hover:text-blue-600">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="text-gray-600 hover:text-blue-600">
                                <i class="fab fa-pinterest"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Merchant Info -->
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center">
                        <div class="bg-gray-200 border-2 border-dashed rounded-xl w-16 h-16" />
                        <div class="ml-4">
                            <h3 class="font-medium text-gray-900">Sold by</h3>
                            <p class="text-gray-600"><?= htmlspecialchars($product['merchant_email']) ?></p>
                            <div class="flex items-center mt-1">
                                <div class="flex">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star text-yellow-400 text-sm"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="ml-2 text-sm text-gray-600">(4.8)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Product Details Tabs -->
        <div class="bg-white rounded-lg shadow-md mb-12" x-data="{ activeTab: 'description' }">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button @click="activeTab = 'description'" 
                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'description' }"
                            class="py-4 px-6 text-center border-b-2 font-medium text-sm">
                        Description
                    </button>
                    <button @click="activeTab = 'specifications'" 
                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'specifications' }"
                            class="py-4 px-6 text-center border-b-2 font-medium text-sm">
                        Specifications
                    </button>
                    <button @click="activeTab = 'reviews'" 
                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'reviews' }"
                            class="py-4 px-6 text-center border-b-2 font-medium text-sm">
                        Reviews (<?= $ratingSummary['total_reviews'] ?>)
                    </button>
                    <button @click="activeTab = 'shipping'" 
                            :class="{ 'border-blue-500 text-blue-600': activeTab === 'shipping' }"
                            class="py-4 px-6 text-center border-b-2 font-medium text-sm">
                        Shipping & Returns
                    </button>
                </nav>
            </div>
            
            <div class="p-6">
                <!-- Description Tab -->
                <div x-show="activeTab === 'description'">
                    <h3 class="text-lg font-semibold mb-4">Product Description</h3>
                    <div class="prose max-w-none text-gray-700">
                        <p><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    </div>
                    
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Product Details</h4>
                            <ul class="space-y-1 text-gray-600">
                                <li><strong>SKU:</strong> <?= htmlspecialchars($product['sku'] ?? 'N/A') ?></li>
                                <li><strong>Category:</strong> <?= htmlspecialchars($product['category'] ?? 'Uncategorized') ?></li>
                                <li><strong>Weight:</strong> <?= $product['weight'] ?> <?= $product['weight_unit'] ?? 'kg' ?></li>
                                <li><strong>In Stock:</strong> <?= $product['quantity'] ?> items</li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Why Buy From Us?</h4>
                            <ul class="space-y-1 text-gray-600">
                                <li>✓ Free shipping on orders over $50</li>
                                <li>✓ 30-day return policy</li>
                                <li>✓ Secure payment processing</li>
                                <li>✓ 24/7 customer support</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Specifications Tab -->
                <div x-show="activeTab === 'specifications'">
                    <h3 class="text-lg font-semibold mb-4">Specifications</h3>
                    <div class="border rounded-lg overflow-hidden">
                        <table class="min-w-full divide-y divide-gray-200">
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Brand</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Generic</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Model Number</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">XYZ-<?= $product['id'] ?></td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Color</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Black</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Material</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Premium Plastic</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Dimensions</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">10 x 5 x 3 inches</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Reviews Tab -->
                <div x-show="activeTab === 'reviews'">
                    <?php if ($ratingSummary['total_reviews'] > 0): ?>
                        <!-- Rating Summary -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                            <div class="text-center">
                                <div class="text-4xl font-bold text-gray-900 mb-2"><?= number_format($ratingSummary['average_rating'], 1) ?></div>
                                <div class="flex justify-center mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?= $i <= round($ratingSummary['average_rating']) ? 'text-yellow-400' : 'text-gray-300' ?> text-xl"></i>
                                    <?php endfor; ?>
                                </div>
                                <div class="text-gray-600"><?= $ratingSummary['total_reviews'] ?> review<?= $ratingSummary['total_reviews'] != 1 ? 's' : '' ?></div>
                                <?php if ($ratingSummary['verified_purchases'] > 0): ?>
                                    <div class="text-sm text-green-600 mt-1"><?= $ratingSummary['verified_purchases'] ?> verified purchases</div>
                                <?php endif; ?>
                            </div>
                            
                            <div>
                                <?php 
                                $starCounts = [
                                    5 => $ratingSummary['five_star'],
                                    4 => $ratingSummary['four_star'], 
                                    3 => $ratingSummary['three_star'],
                                    2 => $ratingSummary['two_star'],
                                    1 => $ratingSummary['one_star']
                                ];
                                ?>
                                <?php foreach ($starCounts as $stars => $count): ?>
                                    <div class="flex items-center mb-2">
                                        <span class="text-sm w-8"><?= $stars ?></span>
                                        <i class="fas fa-star text-yellow-400 text-sm mr-2"></i>
                                        <div class="flex-1 bg-gray-200 rounded-full h-2 mr-3">
                                            <div class="bg-yellow-400 h-2 rounded-full" style="width: <?= $ratingSummary['total_reviews'] > 0 ? ($count / $ratingSummary['total_reviews'] * 100) : 0 ?>%"></div>
                                        </div>
                                        <span class="text-sm text-gray-600 w-8"><?= $count ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Review Form -->
                    <?php if ($canUserReview): ?>
                        <div id="review-form" class="bg-gray-50 rounded-lg p-6 mb-8">
                            <h3 class="text-lg font-semibold mb-4">Write Your Review</h3>
                            
                            <?php if ($reviewError): ?>
                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                                    <?= htmlspecialchars($reviewError) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($reviewSuccess): ?>
                                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                                    <?= htmlspecialchars($reviewSuccess) ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" class="space-y-4">
                                <?= Security::getCSRFInput() ?>
                                
                                <!-- Rating -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Rating *</label>
                                    <div class="flex space-x-1" x-data="{ rating: 0, hoverRating: 0 }">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <button type="button" 
                                                    @click="rating = <?= $i ?>" 
                                                    @mouseover="hoverRating = <?= $i ?>" 
                                                    @mouseleave="hoverRating = 0"
                                                    class="text-2xl focus:outline-none transition-colors duration-200"
                                                    :class="(hoverRating >= <?= $i ?> || (hoverRating === 0 && rating >= <?= $i ?>)) ? 'text-yellow-400' : 'text-gray-300'">
                                                <i class="fas fa-star"></i>
                                            </button>
                                        <?php endfor; ?>
                                        <input type="hidden" name="rating" :value="rating" required>
                                    </div>
                                </div>
                                
                                <!-- Title -->
                                <div>
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Review Title *</label>
                                    <input type="text" name="title" id="title" required maxlength="255"
                                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                           placeholder="Summarize your experience">
                                </div>
                                
                                <!-- Review Text -->
                                <div>
                                    <label for="review_text" class="block text-sm font-medium text-gray-700 mb-2">Your Review *</label>
                                    <textarea name="review_text" id="review_text" rows="4" required maxlength="2000"
                                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                              placeholder="Share your thoughts about this product..."></textarea>
                                    <div class="text-sm text-gray-500 mt-1">Maximum 2000 characters</div>
                                </div>
                                
                                <button type="submit" name="submit_review" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700">
                                    Submit Review
                                </button>
                            </form>
                        </div>
                    <?php elseif (isLoggedIn() && !$canUserReview): ?>
                        <div class="bg-gray-100 rounded-lg p-6 mb-6">
                            <div class="text-center text-gray-600">
                                <?php if ($userReviewStatus['reason'] === 'already_reviewed'): ?>
                                    <i class="fas fa-check-circle text-green-500 text-2xl mb-2"></i>
                                    <p>You have already reviewed this product.</p>
                                <?php else: ?>
                                    <i class="fas fa-shopping-cart text-gray-400 text-2xl mb-2"></i>
                                    <p>Purchase this product to leave a review.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php elseif (!isLoggedIn()): ?>
                        <div class="bg-gray-100 rounded-lg p-6 mb-6">
                            <div class="text-center text-gray-600">
                                <i class="fas fa-user text-gray-400 text-2xl mb-2"></i>
                                <p><a href="login.php" class="text-blue-600 hover:text-blue-800">Login</a> to write a review.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Reviews List -->
                    <?php if (!empty($reviewsData['reviews'])): ?>
                        <div class="bg-white rounded-lg">
                            <!-- Sort Options -->
                            <div class="p-4 border-b">
                                <div class="flex flex-wrap items-center gap-4">
                                    <span class="text-sm font-medium text-gray-700">Sort by:</span>
                                    <select onchange="window.location.href='?id=<?= $productId ?>&sort=' + this.value" class="text-sm border border-gray-300 rounded px-2 py-1">
                                        <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest</option>
                                        <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                        <option value="highest_rated" <?= $sortBy === 'highest_rated' ? 'selected' : '' ?>>Highest Rated</option>
                                        <option value="lowest_rated" <?= $sortBy === 'lowest_rated' ? 'selected' : '' ?>>Lowest Rated</option>
                                        <option value="most_helpful" <?= $sortBy === 'most_helpful' ? 'selected' : '' ?>>Most Helpful</option>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Reviews -->
                            <div class="divide-y divide-gray-200">
                                <?php foreach ($reviewsData['reviews'] as $review): ?>
                                    <div class="p-6">
                                        <div class="flex items-start justify-between mb-3">
                                            <div>
                                                <div class="flex items-center mb-1">
                                                    <span class="font-medium text-gray-900">
                                                        <?= htmlspecialchars(trim($review['first_name'] . ' ' . $review['last_name']) ?: 'Anonymous') ?>
                                                    </span>
                                                    <?php if ($review['is_verified_purchase']): ?>
                                                        <span class="ml-2 bg-green-100 text-green-800 text-xs px-2 py-1 rounded">Verified Purchase</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex items-center">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?= $i <= $review['rating'] ? 'text-yellow-400' : 'text-gray-300' ?> text-sm"></i>
                                                    <?php endfor; ?>
                                                    <span class="ml-2 text-sm text-gray-600"><?= date('M j, Y', strtotime($review['created_at'])) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($review['title']): ?>
                                            <h4 class="font-medium text-gray-900 mb-2"><?= htmlspecialchars($review['title']) ?></h4>
                                        <?php endif; ?>
                                        
                                        <p class="text-gray-700 mb-3"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
                                        
                                        <?php if ($review['admin_response']): ?>
                                            <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-3">
                                                <div class="text-sm font-medium text-blue-800 mb-1">Response from VentDepot:</div>
                                                <div class="text-sm text-blue-700"><?= nl2br(htmlspecialchars($review['admin_response'])) ?></div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Review Actions -->
                                        <?php if (isLoggedIn()): ?>
                                            <div class="flex items-center space-x-4 text-sm">
                                                <span class="text-gray-600">Was this helpful?</span>
                                                <button onclick="voteOnReview(<?= $review['id'] ?>, 'helpful')" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-thumbs-up mr-1"></i>Yes (<?= $review['helpful_votes'] ?>)
                                                </button>
                                                <button onclick="voteOnReview(<?= $review['id'] ?>, 'unhelpful')" class="text-blue-600 hover:text-blue-800">
                                                    <i class="fas fa-thumbs-down mr-1"></i>No (<?= $review['unhelpful_votes'] ?>)
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($reviewsData['total_pages'] > 1): ?>
                                <div class="p-4 border-t">
                                    <div class="flex justify-center space-x-2">
                                        <?php for ($i = 1; $i <= $reviewsData['total_pages']; $i++): ?>
                                            <a href="?id=<?= $productId ?>&review_page=<?= $i ?>&sort=<?= urlencode($sortBy) ?>" 
                                               class="px-3 py-2 text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' ?> rounded">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded-lg p-8 text-center">
                            <i class="fas fa-star text-gray-300 text-4xl mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Reviews Yet</h3>
                            <p class="text-gray-600">Be the first to review this product!</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Shipping & Returns Tab -->
                <div x-show="activeTab === 'shipping'">
                    <h3 class="text-lg font-semibold mb-4">Shipping & Returns</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Shipping Information</h4>
                            <ul class="space-y-2 text-gray-600">
                                <li class="flex items-start">
                                    <i class="fas fa-truck mt-1 mr-3 text-blue-500"></i>
                                    <div>
                                        <span class="font-medium">Free Shipping</span>
                                        <p>On orders over $50.00</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-clock mt-1 mr-3 text-blue-500"></i>
                                    <div>
                                        <span class="font-medium">Delivery Time</span>
                                        <p>3-5 business days</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-map-marker-alt mt-1 mr-3 text-blue-500"></i>
                                    <div>
                                        <span class="font-medium">Shipping To</span>
                                        <p>United States, Canada, UK, EU</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-3">Return Policy</h4>
                            <ul class="space-y-2 text-gray-600">
                                <li class="flex items-start">
                                    <i class="fas fa-undo mt-1 mr-3 text-green-500"></i>
                                    <div>
                                        <span class="font-medium">30-Day Returns</span>
                                        <p>Full refund or exchange</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-tags mt-1 mr-3 text-green-500"></i>
                                    <div>
                                        <span class="font-medium">Return Shipping</span>
                                        <p>We'll cover return shipping costs</p>
                                    </div>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-file-invoice mt-1 mr-3 text-green-500"></i>
                                    <div>
                                        <span class="font-medium">Easy Process</span>
                                        <p>Simple online return request</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                    <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                        <h4 class="font-semibold text-gray-900 mb-2">Need Help?</h4>
                        <p class="text-gray-600">Contact our customer service team for assistance with orders, returns, or product questions.</p>
                        <a href="contact.php" class="mt-2 inline-block text-blue-600 hover:text-blue-800 font-medium">
                            Contact Customer Service <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($relatedProducts)): ?>
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-6">Related Products</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($relatedProducts as $relatedProduct): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition-shadow duration-200">
                            <div class="aspect-w-1 aspect-h-1 w-full overflow-hidden">
                                <img src="<?= htmlspecialchars($relatedProduct['image_url'] ?? 'https://via.placeholder.com/300x300') ?>" 
                                     alt="<?= htmlspecialchars($relatedProduct['name']) ?>" 
                                     class="w-full h-48 object-cover">
                            </div>
                            <div class="p-4">
                                <h3 class="text-sm font-medium text-gray-900 line-clamp-2">
                                    <a href="product.php?id=<?= $relatedProduct['id'] ?>" class="hover:text-blue-600">
                                        <?= htmlspecialchars($relatedProduct['name']) ?>
                                    </a>
                                </h3>
                                <div class="mt-2 flex items-center">
                                    <span class="text-sm font-medium text-gray-900">$<?= number_format($relatedProduct['price'], 2) ?></span>
                                </div>
                                <button onclick="addToCart(<?= $relatedProduct['id'] ?>)" 
                                        class="mt-3 w-full bg-blue-600 text-white py-1.5 text-sm rounded hover:bg-blue-700 transition-colors">
                                    Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Quantity selector
        document.getElementById('decreaseQty').addEventListener('click', function() {
            const qtyInput = document.getElementById('quantity');
            const currentValue = parseInt(qtyInput.value);
            if (currentValue > 1) {
                qtyInput.value = currentValue - 1;
            }
        });

        document.getElementById('increaseQty').addEventListener('click', function() {
            const qtyInput = document.getElementById('quantity');
            const currentValue = parseInt(qtyInput.value);
            const maxValue = parseInt(qtyInput.max);
            if (currentValue < maxValue) {
                qtyInput.value = currentValue + 1;
            }
        });

        // Add to cart functionality
        document.getElementById('addToCartBtn').addEventListener('click', function() {
            const productId = this.getAttribute('data-product-id');
            const quantity = parseInt(document.getElementById('quantity').value);
            
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const button = document.getElementById('addToCartBtn');
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-check mr-2"></i> Added to Cart!';
                    button.classList.add('bg-green-600');
                    button.classList.remove('bg-blue-600');

                    setTimeout(() => {
                        button.innerHTML = originalText;
                        button.classList.remove('bg-green-600');
                        button.classList.add('bg-blue-600');
                    }, 2000);

                    // Update cart count in navigation
                    const cartCountElement = document.querySelector('a[href="cart.php"] .absolute');
                    if (cartCountElement) {
                        cartCountElement.textContent = data.cart_count;
                    }
                } else {
                    alert('Error adding to cart: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error adding to cart');
            });
        });

        // Buy Now functionality
        document.getElementById('buyNowBtn').addEventListener('click', function() {
            const productId = document.getElementById('addToCartBtn').getAttribute('data-product-id');
            const quantity = parseInt(document.getElementById('quantity').value);
            
            // Add to cart first
            fetch('api/cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'add',
                    product_id: productId,
                    quantity: quantity
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Redirect to checkout
                    window.location.href = 'checkout.php';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing order');
            });
        });

        // Image gallery functionality
        document.querySelectorAll('.product-image-thumb').forEach(thumb => {
            thumb.addEventListener('click', function() {
                const mainImage = document.getElementById('mainImage');
                mainImage.src = this.src.replace('100x100', '500x500');
                
                // Update active border
                document.querySelectorAll('.product-image-thumb').forEach(t => {
                    t.classList.remove('border-blue-500');
                    t.classList.add('border-gray-200');
                });
                this.classList.remove('border-gray-200');
                this.classList.add('border-blue-500');
            });
        });

        // Review voting function
        function voteOnReview(reviewId, voteType) {
            fetch('api/reviews.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'vote',
                    review_id: reviewId,
                    vote_type: voteType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload page to show updated vote counts
                    location.reload();
                } else {
                    alert('Error: ' + (data.message || 'Failed to record vote'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error recording vote');
            });
        }

        // Simple zoom functionality
        function initZoom() {
            const img = document.getElementById('mainImage');
            const lens = document.getElementById('zoomLens');
            const result = document.getElementById('zoomResult');
            
            if (!img || !lens || !result) return;
            
            // Create lens
            lens.style.display = 'none';
            result.style.display = 'none';
            
            // Insert result viewer after the image
            img.parentNode.insertBefore(result, img.nextSibling);
            
            // Set background properties for the result div
            result.style.backgroundImage = "url('" + img.src + "')";
            result.style.backgroundRepeat = "no-repeat";
            
            // Calculate the ratio between result div and lens
            const cx = result.offsetWidth / lens.offsetWidth;
            const cy = result.offsetHeight / lens.offsetHeight;
            
            // Set background size for the result div
            result.style.backgroundSize = (img.width * cx) + "px " + (img.height * cy) + "px";
            
            // Execute a function when someone moves the cursor over the image
            img.addEventListener("mousemove", moveLens);
            lens.addEventListener("mousemove", moveLens);
            img.addEventListener("mouseleave", () => {
                lens.style.display = "none";
                result.style.display = "none";
            });
            
            function moveLens(e) {
                let pos, x, y;
                
                // Prevent any other actions that may occur when moving over the image
                e.preventDefault();
                
                // Get the cursor's x and y positions
                pos = getCursorPos(e);
                
                // Calculate the position of the lens
                x = pos.x - (lens.offsetWidth / 2);
                y = pos.y - (lens.offsetHeight / 2);
                
                // Prevent the lens from being positioned outside the image
                if (x > img.width - lens.offsetWidth) {x = img.width - lens.offsetWidth;}
                if (x < 0) {x = 0;}
                if (y > img.height - lens.offsetHeight) {y = img.height - lens.offsetHeight;}
                if (y < 0) {y = 0;}
                
                // Set the position of the lens
                lens.style.left = x + "px";
                lens.style.top = y + "px";
                
                // Display what the lens "sees"
                result.style.backgroundPosition = "-" + (x * cx) + "px -" + (y * cy) + "px";
            }
            
            function getCursorPos(e) {
                let a, x = 0, y = 0;
                e = e || window.event;
                
                // Get the x and y positions of the image
                a = img.getBoundingClientRect();
                
                // Calculate the cursor's x and y coordinates, relative to the image
                x = e.pageX - a.left;
                y = e.pageY - a.top;
                
                // Consider any scrolling of the page
                x = x - window.pageXOffset;
                y = y - window.pageYOffset;
                
                return {x : x, y : y};
            }
            
            // Show lens on hover
            img.addEventListener("mouseenter", () => {
                lens.style.display = "block";
                result.style.display = "block";
            });
        }

        // Initialize zoom when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initZoom();
        });
    </script>
</body>
</html>