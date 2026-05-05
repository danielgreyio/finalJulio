<?php

function getDummyProducts() {
    return [
        1 => [
            'id' => 1,
            'name' => 'Wireless Noise-Canceling Headphones',
            'category' => 'Electronics',
            'price' => 299.99,
            'compare_price' => 349.99,
            'description' => "Experience premium sound quality with our latest wireless noise-canceling headphones.\n\nKey features:\n- Active Noise Cancellation\n- 30-hour battery life\n- Bluetooth 5.0 connectivity\n- Plush ear cushions for all-day comfort\n\nPerfect for commuters, travelers, and audiophiles.",
            'short_description' => 'Premium wireless headphones with active noise cancellation.',
            'image_url' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&q=80',
            'quantity' => 50,
            'weight' => 0.5,
            'dimensions' => ['length' => 20, 'width' => 15, 'height' => 10],
            'merchant_id' => 1,
            'merchant_email' => 'merchant@demo.com',
            'sku' => 'EL-HEAD-001',
            'meta_title' => 'Wireless Headphones - Best Noise Canceling',
            'meta_description' => 'Buy the best wireless noise-canceling headphones.',
            'meta_keywords' => 'headphones, wireless, audio'
        ],
        2 => [
            'id' => 2,
            'name' => 'Smart Fitness Watch',
            'category' => 'Electronics',
            'price' => 149.50,
            'compare_price' => 199.99,
            'description' => "Track your fitness goals with precision.\n\nFeatures:\n- Heart rate monitoring\n- Sleep tracking\n- GPS built-in\n- Water resistant up to 50m\n- 7-day battery life",
            'short_description' => 'Advanced fitness tracker with heart rate monitor.',
            'image_url' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500&q=80',
            'quantity' => 30,
            'weight' => 0.1,
            'dimensions' => ['length' => 10, 'width' => 10, 'height' => 5],
            'merchant_id' => 1,
            'merchant_email' => 'merchant@demo.com',
            'sku' => 'EL-WATCH-002',
             'meta_title' => 'Smart Fitness Watch',
            'meta_description' => 'Track your health with our smart fitness watch.',
            'meta_keywords' => 'watch, fitness, smart'
        ],
        3 => [
            'id' => 3,
            'name' => 'Ergonomic Office Chair',
            'category' => 'Home & Garden',
            'price' => 249.00,
            'compare_price' => 0,
            'description' => "Work in comfort with our ergonomic office chair designed for lumbar support.",
            'short_description' => 'High-back ergonomic office chair.',
            'image_url' => 'https://images.unsplash.com/photo-1580480055273-228ff5388ef8?w=500&q=80',
            'quantity' => 15,
            'weight' => 15.0,
            'dimensions' => ['length' => 60, 'width' => 60, 'height' => 120],
            'merchant_id' => 2,
            'merchant_email' => 'furniture@demo.com',
            'sku' => 'HG-CHAIR-003',
             'meta_title' => 'Ergonomic Office Chair',
            'meta_description' => 'Best ergonomic chair for your home office.',
            'meta_keywords' => 'chair, office, furniture'
        ],
         4 => [
            'id' => 4,
            'name' => 'Professional DSLR Camera',
            'category' => 'Electronics',
            'price' => 1299.00,
            'compare_price' => 1499.00,
            'description' => "Capture life's moments in stunning detail with this professional DSLR camera.",
            'short_description' => 'High-resolution DSLR camera body.',
            'image_url' => 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=500&q=80',
            'quantity' => 5,
            'weight' => 1.2,
            'dimensions' => ['length' => 25, 'width' => 20, 'height' => 15],
            'merchant_id' => 1,
            'merchant_email' => 'merchant@demo.com',
            'sku' => 'EL-CAM-004',
            'meta_title' => 'Professional DSLR Camera',
            'meta_description' => 'Capture stunning photos with our DSLR camera.',
            'meta_keywords' => 'camera, dslr, photography'
        ],
        5 => [
            'id' => 5,
            'name' => 'Organic Green Tea',
            'category' => 'Health',
            'price' => 12.99,
            'compare_price' => 0,
            'description' => "Premium organic green tea leaves sourced from the finest gardens.",
            'short_description' => '100% Organic Green Tea Leaves.',
            'image_url' => 'https://images.unsplash.com/photo-1627435601361-ec25f5b1d0e5?w=500&q=80',
            'quantity' => 100,
            'weight' => 0.2,
            'dimensions' => ['length' => 10, 'width' => 8, 'height' => 15],
            'merchant_id' => 3,
            'merchant_email' => 'health@demo.com',
            'sku' => 'HL-TEA-005',
            'meta_title' => 'Organic Green Tea',
            'meta_description' => 'Fresh and organic green tea.',
            'meta_keywords' => 'tea, green tea, health'
        ],
        6 => [
            'id' => 6,
            'name' => 'Running Shoes',
            'category' => 'Sports',
            'price' => 89.99,
            'compare_price' => 119.99,
            'description' => "Lightweight running shoes designed for speed and comfort.",
            'short_description' => 'Breathable running shoes.',
            'image_url' => 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?w=500&q=80',
            'quantity' => 45,
            'weight' => 0.8,
            'merchant_id' => 4,
            'merchant_email' => 'sports@demo.com',
            'sku' => 'SP-SHOE-006',
            'meta_title' => 'Running Shoes',
            'meta_description' => 'Comfortable running shoes for athletes.',
            'meta_keywords' => 'shoes, running, sports'
        ],
         7 => [
            'id' => 7,
            'name' => 'Modern Coffee Table',
            'category' => 'Home & Garden',
            'price' => 159.00,
            'compare_price' => 0,
            'description' => "A sleek and modern coffee table to faster your living room decor.",
            'short_description' => 'Minimalist wooden coffee table.',
            'image_url' => 'https://images.unsplash.com/photo-1533090481720-856c6e3c1fdc?w=500&q=80',
            'quantity' => 20,
            'weight' => 10.0,
            'merchant_id' => 2,
            'merchant_email' => 'furniture@demo.com',
            'sku' => 'HG-TABLE-007',
            'meta_title' => 'Modern Coffee Table',
            'meta_description' => 'Stylish coffee table for your home.',
            'meta_keywords' => 'table, coffee table, furniture'
        ],
        8 => [
            'id' => 8,
            'name' => 'Designer Sunglasses',
            'category' => 'Fashion',
            'price' => 199.50,
            'compare_price' => 250.00,
            'description' => "Protect your eyes in style with these designer sunglasses.",
            'short_description' => 'UV400 protection stylish sunglasses.',
            'image_url' => 'https://images.unsplash.com/photo-1511499767150-a48a237f0083?w=500&q=80',
            'quantity' => 60,
            'weight' => 0.1,
            'merchant_id' => 5,
            'merchant_email' => 'fashion@demo.com',
            'sku' => 'FS-GLASS-008',
            'meta_title' => 'Designer Sunglasses',
            'meta_description' => 'Fashionable sunglasses with UV protection.',
            'meta_keywords' => 'sunglasses, fashion, accessories'
        ],
        9 => [
            'id' => 9,
            'name' => 'Mechanical Gaming Keyboard',
            'category' => 'Electronics',
            'price' => 129.99,
            'compare_price' => 0,
            'description' => "RGB mechanical gaming keyboard with blue switches.",
            'short_description' => 'RGB mechanical keyboard.',
            'image_url' => 'https://images.unsplash.com/photo-1595225476474-87563907a212?w=500&q=80',
            'quantity' => 25,
            'weight' => 1.5,
            'merchant_id' => 1,
            'merchant_email' => 'merchant@demo.com',
            'sku' => 'EL-KEY-009',
            'meta_title' => 'Mechanical Gaming Keyboard',
            'meta_description' => 'Best gaming keyboard for pros.',
            'meta_keywords' => 'keyboard, gaming, electronics'
        ],
        10 => [
            'id' => 10,
            'name' => 'Yoga Mat',
            'category' => 'Sports',
            'price' => 29.99,
            'compare_price' => 0,
            'description' => "Non-slip yoga mat for your daily practice.",
            'short_description' => 'Eco-friendly non-slip yoga mat.',
            'image_url' => 'https://images.unsplash.com/photo-1601925260368-ae2f83cf8b7f?w=500&q=80',
            'quantity' => 100,
            'weight' => 0.8,
            'merchant_id' => 4,
            'merchant_email' => 'sports@demo.com',
            'sku' => 'SP-MAT-010',
            'meta_title' => 'Yoga Mat',
            'meta_description' => 'High quality yoga mat.',
            'meta_keywords' => 'yoga, mat, sports'
        ],
        11 => [
           'id' => 11,
           'name' => 'Leather Wallet',
           'category' => 'Fashion',
           'price' => 49.99,
           'compare_price' => 69.99,
           'description' => "Genuine leather wallet with multiple card slots.",
           'short_description' => 'Classic leather wallet.',
           'image_url' => 'https://images.unsplash.com/photo-1627123424574-181ce5171c98?w=500&q=80',
            'quantity' => 80,
            'weight' => 0.2,
            'merchant_id' => 5,
            'merchant_email' => 'fashion@demo.com',
            'sku' => 'FS-WAL-011',
             'meta_title' => 'Leather Wallet',
            'meta_description' => 'Durable and stylish leather wallet.',
            'meta_keywords' => 'wallet, leather, fashion'
        ],
        12 => [
            'id' => 12,
            'name' => 'Bluetooth Speaker',
            'category' => 'Electronics',
            'price' => 79.99,
            'compare_price' => 99.99,
            'description' => "Portable bluetooth speaker with 360-degree sound.",
            'short_description' => 'Portable waterproof bluetooth speaker.',
            'image_url' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?w=500&q=80',
             'quantity' => 40,
            'weight' => 0.6,
            'dimensions' => ['length' => 18, 'width' => 18, 'height' => 25],
            'merchant_id' => 1,
            'merchant_email' => 'merchant@demo.com',
            'sku' => 'EL-SPK-012',
            'meta_title' => 'Bluetooth Speaker',
            'meta_description' => 'Loud and clear portable speaker.',
            'meta_keywords' => 'speaker, bluetooth, audio'
        ]
    ];
}

function getDummyProduct($id) {
    $products = getDummyProducts();
    if (isset($products[$id])) {
        return $products[$id];
    }
    // Fallback if ID > 12, just return a randomized dummy product based on the first ones
    // or return null. For now, let's wrap around or return ID 1 for testing if out of bounds,
    // but better to return false so we handle 404 naturally.
    return false;
}

function getDummyCartTotal() {
    $cart = $_SESSION['cart'] ?? [];
    $total = 0;
    foreach ($cart as $id => $quantity) {
        $product = getDummyProduct($id);
        if ($product) {
            $total += $product['price'] * $quantity;
        }
    }
    return $total;
}

function getDummyMerchantStats($merchantId) {
    // Return mock stats
    return [
        'total_products' => 120,
        'total_inventory' => 5430,
        'active_products' => 115,
        'out_of_stock' => 5
    ];
}

function getDummyRecentOrders($merchantId) {
    // Generate some mock orders
    $orders = [];
    for ($i = 0; $i < 5; $i++) {
        $orders[] = [
            'id' => 1000 + $i,
            'customer_email' => 'customer' . $i . '@demo.com',
            'created_at' => date('Y-m-d H:i:s', strtotime("-$i days")),
            'total' => rand(50, 500),
            'status' => ['pending', 'shipped', 'delivered'][rand(0, 2)]
        ];
    }
    return $orders;
}

function getDummySalesData($merchantId) {
    $data = [];
    for ($i = 6; $i >= 0; $i--) {
        $data[] = [
            'order_date' => date('Y-m-d', strtotime("-$i days")),
            'order_count' => rand(1, 10),
            'daily_revenue' => rand(100, 1000)
        ];
    }
    return $data;
}

function getDummyTopProducts($merchantId) {
    $products = getDummyProducts();
    return array_map(function($p) {
        $p['order_count'] = rand(10, 100);
        return $p;
    }, array_slice($products, 0, 5));
}

// Cart Helper Functions
if (!function_exists('getCartItems')) {
    function getCartItems() {
        return $_SESSION['cart'] ?? [];
    }
}

if (!function_exists('getCartCount')) {
    function getCartCount() {
        return array_sum($_SESSION['cart'] ?? []);
    }
}
?>
