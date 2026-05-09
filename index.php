<?php
require_once 'config/database.php';
require_once 'classes/CMSFrontend.php';

// Initialize CMS frontend helper
$cms = new CMSFrontend($pdo);

// Fetch featured products from CMS carousel
$featuredProducts = $cms->getProductsInCarousel('Featured Products', 8);

// Construction categories from DB
$stmt = $pdo->prepare("SELECT id, name, slug FROM categories WHERE is_active = TRUE ORDER BY sort_order ASC LIMIT 6");
$stmt->execute();
$dummyCategories = $stmt->fetchAll();

// Fetch featured products for the grid (active products, newest first)
$stmt = $pdo->prepare("SELECT * FROM products WHERE is_active = TRUE ORDER BY created_at DESC LIMIT 36");
$stmt->execute();
$defaultProducts = $stmt->fetchAll();

// Get SEO metadata for homepage
$seoMetadata = $cms->getSEOMetadata('homepage');
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>VentDepot — Materiales de Construcción en México</title>
<meta name="description" content="VentDepot: el marketplace líder de materiales de construcción en México. Cemento, varilla, herramientas, plomería, eléctrico y más.">
<link href="https://fonts.googleapis.com" rel="preconnect"/>
<link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@200..800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#f45925",
                        "background-light": "#f8f6f5",
                        "background-dark": "#221410",
                    },
                    fontFamily: {
                        "display": ["Manrope", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "2xl": "1rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
<style>
.hide-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .hide-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="flex min-h-screen w-full flex-col overflow-x-hidden bg-background-light font-display text-[#181311] group/design-root dark:bg-background-dark dark:text-white">
<header class="sticky top-0 z-40 flex w-full flex-col bg-white shadow-sm dark:bg-[#1a1a1a] dark:shadow-gray-900/10">
<div class="hidden border-b border-[#f5f1f0] px-4 py-2 lg:flex lg:justify-end lg:px-10 dark:border-[#333]">
<div class="flex items-center gap-6 text-xs font-medium text-[#8a6b60] dark:text-gray-400">
<a class="hover:text-primary transition-colors" href="#">Sell on VentDepot</a>
<a class="hover:text-primary transition-colors" href="#">Help Center</a>
<a class="hover:text-primary transition-colors" href="#">Buyer Protection</a>
<a class="flex items-center gap-1 hover:text-primary transition-colors" href="#">
<span class="material-symbols-outlined text-[16px]">phone_iphone</span>
                App
            </a>
</div>
</div>
<div class="flex items-center justify-between gap-4 px-4 py-4 lg:px-10">
<div class="flex shrink-0 items-center gap-2">
<div class="flex size-8 items-center justify-center rounded-lg bg-primary text-white">
<span class="material-symbols-outlined">shopping_bag</span>
</div>
<!-- Changed logo to VentDepot -->
<a href="index.php" class="text-xl font-bold tracking-tight text-[#181311] lg:text-2xl dark:text-white">VentDepot</a>
</div>
<div class="hidden max-w-[700px] flex-1 lg:block">
<div class="flex h-11 w-full items-center rounded-full border border-gray-300 bg-white p-[2px] dark:border-gray-700 dark:bg-[#2a2a2a]">
<div class="relative flex h-full flex-1 items-center">
<input class="w-full border-none bg-transparent px-4 text-sm text-[#181311] placeholder-[#8a6b60] focus:ring-0 dark:text-white dark:placeholder-gray-500" placeholder="Search 10,000+ products..." type="text"/>
</div>
<button class="flex h-full items-center justify-center rounded-full bg-gray-100 px-6 text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
<span class="material-symbols-outlined">search</span>
</button>
</div>
</div>
<div class="flex shrink-0 items-center gap-3 lg:gap-6">
<button class="flex items-center justify-center rounded-full p-2 text-[#181311] lg:hidden dark:text-white">
<span class="material-symbols-outlined">search</span>
</button>
<div class="hidden items-center gap-2 lg:flex">
<span class="material-symbols-outlined text-[28px] text-[#181311] dark:text-gray-300">person</span>
<div class="flex flex-col">
<?php if (isLoggedIn()): ?>
    <span class="text-xs text-[#8a6b60] dark:text-gray-400">Welcome, <?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span>
    <div class="flex gap-1 text-xs font-bold text-[#181311] dark:text-white">
        <a class="hover:text-primary" href="profile.php">Profile</a> / <a class="hover:text-primary" href="logout.php">Logout</a>
    </div>
<?php else: ?>
    <span class="text-xs text-[#8a6b60] dark:text-gray-400">Welcome</span>
    <div class="flex gap-1 text-xs font-bold text-[#181311] dark:text-white">
        <a class="hover:text-primary" href="login.php">Sign in</a> / <a class="hover:text-primary" href="register.php">Join</a>
    </div>
<?php endif; ?>
</div>
</div>
<button class="relative hidden items-center justify-center rounded-lg p-2 text-[#181311] hover:bg-gray-100 lg:flex dark:text-white dark:hover:bg-[#333]">
<span class="material-symbols-outlined">favorite</span>
<span class="absolute right-1 top-1 flex h-2 w-2">
<span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-primary opacity-75"></span>
<span class="relative inline-flex h-2 w-2 rounded-full bg-primary"></span>
</span>
</button>
<a href="cart.php" class="relative flex items-center justify-center gap-2 rounded-lg bg-transparent px-3 py-2 text-[#181311] hover:bg-gray-100 dark:bg-transparent dark:text-white dark:hover:bg-[#333]">
<span class="material-symbols-outlined">shopping_cart</span>
<?php 
// Calculate cart total if available in session/cookie, else mock 0
$cartTotal = isset($_SESSION['cart_total']) ? $_SESSION['cart_total'] : 0.00;
$cartCount = isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : 0;
?>
<span class="hidden text-sm font-bold lg:block">$<?= number_format($cartTotal, 2) ?></span>
<span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-primary text-[10px] font-bold text-white"><?= $cartCount ?></span>
</a>
</div>
</div>
<div class="hidden border-t border-[#f5f1f0] px-10 py-2.5 lg:flex dark:border-[#333]">
<nav class="flex items-center gap-8 text-sm font-medium text-gray-700 dark:text-gray-300">
<a class="flex items-center gap-2 font-bold text-[#181311] dark:text-white" href="#"><span class="material-symbols-outlined">menu</span>All Categories</a>
<a class="text-primary font-bold" href="#">Special Offers</a>
<a class="hover:text-primary" href="#">Choice</a>
<a class="hover:text-primary" href="#">SuperDeals</a>
<a class="hover:text-primary" href="#">Free Shipping</a>
<a class="hover:text-primary" href="#">Home &amp; Pajamas</a>
<a class="hover:text-primary" href="#">Women's Fashion</a>
<a class="hover:text-primary" href="#">Motor</a>
<a class="hover:text-primary" href="#">Jewelry &amp; Watches</a>
<a class="flex items-center gap-1 hover:text-primary" href="#">More <span class="material-symbols-outlined text-base">expand_more</span></a>
</nav>
</div>
</header>
<main class="flex-1 px-4 py-6 lg:px-10">
<div class="mx-auto flex max-w-[1400px] flex-col gap-6">
<div class="flex flex-col gap-6">
<section class="flex flex-col gap-4">
<div class="relative w-full overflow-hidden rounded-xl bg-[#006b52] p-8 text-white">
<div class="absolute inset-0 z-0 h-full w-full bg-cover bg-right" data-alt="Abstract green background with gift boxes and electronics" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuCDbSLsxGzSGFYBu9LhdB4qlkQu-E3Pj6NxfYoBUu-59SRF2k0QmrwuJJw4f6hGiZSJZntfLOuRpoLWIyHhf9fGp0N7fXOmNeDTWCznBdbi-v0k9DZ7eFZPB6SeugTw7YVhY5pffX9ixHDzdA-PvioWWo0uIkNtd0ta4irhtW2Nr_tV1Pm8E6s5W-RmhCDmCDneqa3mTf3Y6oUjzf5PurF1qOZZ6OxK3IaXSIQS-y-rWDpVuag44wh7e4dWQJER4gHoxYsSRX51b-M');"></div>
<div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center">
<div class="flex flex-1 flex-col">
<p class="font-semibold">The Promotion Ends: 14 days, 23:59 (CT)</p>
<h1 class="text-5xl font-extrabold uppercase tracking-tight text-white">Gift Season <span class="material-symbols-outlined text-4xl">arrow_forward</span></h1>
</div>
<div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
<div class="flex flex-col items-center justify-center rounded-lg bg-white/20 p-4 text-center backdrop-blur-sm">
<span class="text-2xl font-bold">-$380</span>
<span class="text-xs">on +$3,000</span>
<span class="mt-2 rounded-full bg-white px-3 py-1 text-xs font-bold text-gray-800">Code: MXGS6</span>
</div>
<div class="flex flex-col items-center justify-center rounded-lg bg-white/20 p-4 text-center backdrop-blur-sm">
<span class="text-2xl font-bold">-$300</span>
<span class="text-xs">on +$2,200</span>
<span class="mt-2 rounded-full bg-white px-3 py-1 text-xs font-bold text-gray-800">Code: MXGS5</span>
</div>
<div class="flex flex-col items-center justify-center rounded-lg bg-white/20 p-4 text-center backdrop-blur-sm">
<span class="text-2xl font-bold">-$190</span>
<span class="text-xs">on +$1,500</span>
<span class="mt-2 rounded-full bg-white px-3 py-1 text-xs font-bold text-gray-800">Code: MXGS4</span>
</div>
<div class="flex h-full min-h-[120px] items-center justify-center rounded-lg bg-white p-4">
<div class="h-full w-full bg-contain bg-center bg-no-repeat" data-alt="Image of various small products" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuAehzWZwV8WdJ6gj7UGPFvCjW-8o4Wyp3RcxUa9V1pRP7KLjFPfADRrpIhp8-VBFvtGB75Tz1toyu60YLx86GQzc53Zep024Qe1x5nsDBl2M6nxUEKJQvPdjhq-I5wyn79RAKjoPjKu5jUA0fYtADVqw7rmHPl1RMZtOO38aWKYTCAWckuMChW8zfdhJ2J1nFMg4mNVc0SbJdfFh-z6LO63yFT8ze5tC570JAlVJ4vE-wB3fJHTJBS6synoe5n2N_CBguMVCaf7TUE');"></div>
</div>
</div>
</div>
</div>
<div class="grid grid-cols-1 gap-4 rounded-xl bg-white p-3 shadow-sm dark:bg-[#1a1a1a] md:grid-cols-3">
<div class="flex items-center justify-center gap-2 text-sm text-gray-700 dark:text-gray-300">
<span class="material-symbols-outlined text-primary">verified</span>
<span>Extra Star Discount of 16.79%</span>
</div>
<div class="flex items-center justify-center gap-2 text-sm text-gray-700 dark:text-gray-300">
<span class="material-symbols-outlined text-primary">local_shipping</span>
<span>Fast Shipping</span>
</div>
<div class="flex items-center justify-center gap-2 text-sm text-gray-700 dark:text-gray-300">
<span class="material-symbols-outlined text-primary">assignment_return</span>
<span>Returns within 90 days</span>
</div>
</div>
</section>
<section class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-[#1a1a1a]">
<div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
<div class="flex items-center gap-3">
<h2 class="text-xl font-bold uppercase tracking-wide text-primary">Flash Deals</h2>
<div class="flex items-center gap-1 text-sm text-gray-600 dark:text-gray-400">
<span>Ending in</span>
<div class="flex items-center gap-1">
<span class="flex h-7 w-7 items-center justify-center rounded bg-gray-800 text-sm font-bold text-white dark:bg-gray-700">03</span>
<span class="font-bold">:</span>
<span class="flex h-7 w-7 items-center justify-center rounded bg-gray-800 text-sm font-bold text-white dark:bg-gray-700">24</span>
<span class="font-bold">:</span>
<span class="flex h-7 w-7 items-center justify-center rounded bg-gray-800 text-sm font-bold text-white dark:bg-gray-700">55</span>
</div>
</div>
</div>
<a class="flex items-center gap-1 rounded-full border border-primary px-4 py-1.5 text-sm font-medium text-primary transition-colors hover:bg-primary/10" href="#">View all<span class="material-symbols-outlined text-lg">chevron_right</span></a>
</div>
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
<!-- Flash Deals Loop -->
<?php foreach ($flashDeals as $product): ?>
<div class="group flex flex-col overflow-hidden rounded-lg border border-transparent transition-all hover:border-primary hover:shadow-lg dark:hover:border-primary">
<a href="product.php?id=<?= $product['id'] ?>" class="block relative aspect-square w-full overflow-hidden bg-[#f5f1f0] dark:bg-[#2a2a2a]">
<div class="h-full w-full bg-cover bg-center transition-transform duration-300 group-hover:scale-110" data-alt="<?= htmlspecialchars($product['name']) ?>" style="background-image: url('<?= $product['image_url'] ?>');"></div>
</a>
<div class="flex flex-1 flex-col p-3">
<a href="product.php?id=<?= $product['id'] ?>">
<h3 class="text-sm font-medium text-[#181311] line-clamp-2 dark:text-white"><?= htmlspecialchars($product['name']) ?></h3>
</a>
<div class="mt-2 flex items-center gap-1">
<?php if ($product['compare_price'] > 0): ?>
<span class="text-xs text-gray-500 line-through">$<?= number_format($product['compare_price'], 2) ?></span>
<?php endif; ?>
<span class="text-sm font-bold text-primary">$<?= number_format($product['price'], 2) ?></span>
</div>
<div class="mt-2 flex items-center justify-between">
<div class="flex items-center gap-1 text-xs text-gray-500">
<span class="material-symbols-outlined text-[14px] text-yellow-400">star</span>
<span>4.8</span>
</div>
<button onclick="addToCart(<?= $product['id'] ?>)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-white transition-colors hover:bg-[#e04a1d]">
<span class="material-symbols-outlined text-[18px]">shopping_cart</span>
</button>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</section>

<!-- Today's Offers Section -->
<section class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-[#1a1a1a]">
<div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
<div class="flex items-center gap-3">
<h2 class="text-xl font-bold uppercase tracking-wide text-primary">Today's Offers</h2>
</div>
<a class="flex items-center gap-1 rounded-full border border-primary px-4 py-1.5 text-sm font-medium text-primary transition-colors hover:bg-primary/10" href="#">View all<span class="material-symbols-outlined text-lg">chevron_right</span></a>
</div>
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
<!-- Canvas 1: Hot Deals -->
<div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
<h3 class="font-bold text-gray-800 dark:text-white">Hot Deals</h3>
<div class="mt-2 space-y-3">
<?php foreach (array_slice($todayOffers, 0, 3) as $product): ?>
<div class="flex items-center gap-3">
<img src="<?= $product['image_url'] ?>" class="h-12 w-12 bg-gray-200 rounded object-cover">
<div class="flex-1">
<a href="product.php?id=<?= $product['id'] ?>" class="text-sm font-medium block hover:text-primary"><?= htmlspecialchars($product['name']) ?></a>
<div class="flex items-center gap-1">
<span class="text-primary font-bold">$<?= number_format($product['price'], 2) ?></span>
<?php if ($product['compare_price'] > 0): ?>
<span class="text-xs text-gray-500 line-through">$<?= number_format($product['compare_price'], 2) ?></span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- Canvas 2: Super Offers -->
<div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
<h3 class="font-bold text-gray-800 dark:text-white">Super Offers</h3>
<div class="mt-2 space-y-3">
<?php foreach (array_slice($todayOffers, 3, 3) as $product): ?>
<div class="flex items-center gap-3">
<img src="<?= $product['image_url'] ?>" class="h-12 w-12 bg-gray-200 rounded object-cover">
<div class="flex-1">
<a href="product.php?id=<?= $product['id'] ?>" class="text-sm font-medium block hover:text-primary"><?= htmlspecialchars($product['name']) ?></a>
<div class="flex items-center gap-1">
<span class="text-primary font-bold">$<?= number_format($product['price'], 2) ?></span>
<?php if ($product['compare_price'] > 0): ?>
<span class="text-xs text-gray-500 line-through">$<?= number_format($product['compare_price'], 2) ?></span>
<?php endif; ?>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- Canvas 3: Same Day Delivery -->
<div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
<h3 class="font-bold text-gray-800 dark:text-white">Same Day Delivery</h3>
<div class="mt-2 space-y-3">
<?php foreach (array_slice($flashDeals, 0, 3) as $product): ?>
<div class="flex items-center gap-3">
<img src="<?= $product['image_url'] ?>" class="h-12 w-12 bg-gray-200 rounded object-cover">
<div class="flex-1">
<a href="product.php?id=<?= $product['id'] ?>" class="text-sm font-medium block hover:text-primary"><?= htmlspecialchars($product['name']) ?></a>
<div class="flex items-center gap-1">
<span class="text-primary font-bold">$<?= number_format($product['price'], 2) ?></span>
<span class="text-xs bg-blue-100 text-blue-800 px-1 rounded">Fast</span>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>

<!-- Canvas 4: Quick Delivery -->
<div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
<h3 class="font-bold text-gray-800 dark:text-white">Quick Delivery</h3>
<div class="mt-2 space-y-3">
<?php foreach (array_slice($flashDeals, 3, 3) as $product): ?>
<div class="flex items-center gap-3">
<img src="<?= $product['image_url'] ?>" class="h-12 w-12 bg-gray-200 rounded object-cover">
<div class="flex-1">
<a href="product.php?id=<?= $product['id'] ?>" class="text-sm font-medium block hover:text-primary"><?= htmlspecialchars($product['name']) ?></a>
<div class="flex items-center gap-1">
<span class="text-primary font-bold">$<?= number_format($product['price'], 2) ?></span>
<span class="text-xs bg-green-100 text-green-800 px-1 rounded">24h</span>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</section>

<!-- Offers by Category Section -->
<section class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-[#1a1a1a]">
<div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
<div class="flex items-center gap-3">
<h2 class="text-xl font-bold uppercase tracking-wide text-primary">Offers by Category</h2>
</div>
</div>
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
<!-- Left Side: Large Category Banner -->
<div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-8 text-white flex flex-col justify-center">
<h3 class="text-2xl font-bold mb-2">Electronics Sale</h3>
<p class="mb-4">Up to 60% OFF on latest gadgets</p>
<button class="bg-white text-blue-600 hover:bg-gray-100 font-bold py-2 px-6 rounded w-fit">
Shop Electronics
</button>
</div>

<!-- Right Side: 2x3 Album -->
<div class="grid grid-cols-2 grid-rows-3 gap-4">
<?php
$categoryIcons = [
    'construccion'       => 'fa-hard-hat',
    'herramientas'       => 'fa-tools',
    'electrico'          => 'fa-bolt',
    'plomeria'           => 'fa-faucet',
    'seguridad-industrial'=> 'fa-shield-alt',
    'acabados'           => 'fa-paint-roller',
    'ferreteria-general' => 'fa-screwdriver',
];
foreach ($dummyCategories as $cat):
    $icon = $categoryIcons[$cat['slug']] ?? 'fa-box';
?>
<a href="products.php?category=<?= urlencode($cat['slug']) ?>"
   class="bg-gray-100 dark:bg-[#2a2a2a] rounded-lg p-4 flex flex-col items-center justify-center hover:bg-orange-50 dark:hover:bg-[#3a2a1a] transition-colors">
    <div class="flex items-center justify-center w-12 h-12 mb-2 bg-white dark:bg-[#1a1a1a] rounded-xl shadow-sm">
        <i class="fas <?= $icon ?> text-xl text-primary"></i>
    </div>
    <div class="font-medium text-center text-xs"><?= htmlspecialchars($cat['name']) ?></div>
</a>
<?php endforeach; ?>
</div>
</div>
</section>

<!-- Product Album Section -->
<section class="flex flex-col gap-4 rounded-xl bg-white p-4 shadow-sm dark:bg-[#1a1a1a]">
<div class="flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
<div class="flex items-center gap-3">
<h2 class="text-xl font-bold uppercase tracking-wide text-primary">Featured Products</h2>
</div>
<a class="flex items-center gap-1 rounded-full border border-primary px-4 py-1.5 text-sm font-medium text-primary transition-colors hover:bg-primary/10" href="#">View all<span class="material-symbols-outlined text-lg">chevron_right</span></a>
</div>
<div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
<?php foreach ($featuredProducts as $product): ?>
<div class="group flex flex-col overflow-hidden rounded-lg border border-transparent transition-all hover:border-primary hover:shadow-lg dark:hover:border-primary">
<a href="product.php?id=<?= $product['id'] ?>" class="block relative aspect-square w-full overflow-hidden bg-[#f5f1f0] dark:bg-[#2a2a2a]">
<div class="h-full w-full bg-cover bg-center transition-transform duration-300 group-hover:scale-110" data-alt="<?= htmlspecialchars($product['name']) ?>" style="background-image: url('<?= $product['image_url'] ?>');"></div>
<?php if ($product['compare_price'] > $product['price']): ?>
<span class="absolute top-2 left-2 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded">-<?= round((($product['compare_price'] - $product['price']) / $product['compare_price']) * 100) ?>%</span>
<?php endif; ?>
</a>
<div class="flex flex-1 flex-col p-3">
<a href="product.php?id=<?= $product['id'] ?>">
<h3 class="text-sm font-medium text-[#181311] line-clamp-2 dark:text-white"><?= htmlspecialchars($product['name']) ?></h3>
</a>
<div class="mt-2 flex items-center gap-1">
<?php if ($product['compare_price'] && $product['compare_price'] > $product['price']): ?>
<span class="text-xs text-gray-500 line-through">$<?= number_format($product['compare_price'], 2) ?></span>
<?php endif; ?>
<span class="text-sm font-bold text-primary">$<?= number_format($product['price'], 2) ?></span>
</div>
<button onclick="addToCart(<?= $product['id'] ?>)" class="mt-2 w-full rounded-lg bg-primary py-1.5 text-xs font-bold text-white transition-colors hover:bg-[#e04a1d]">
Add to Cart
</button>
</div>
</div>
<?php endforeach; ?>
</div>
<div class="mt-4 text-center">
<button class="rounded-lg border border-primary px-6 py-2 text-sm font-bold text-primary transition-colors hover:bg-primary/10">
Load More
</button>
</div>
</section>
</div>
</div>
</main>

<!-- Footer -->
<footer class="bg-gray-800 text-white py-12">
<div class="max-w-7xl mx-auto px-4">
<div class="grid grid-cols-1 md:grid-cols-4 gap-8">
<div>
<h3 class="text-xl font-bold mb-4">VentDepot</h3>
<p class="text-gray-400">Your trusted online marketplace for quality products from verified merchants.</p>
</div>
<div>
<h4 class="font-semibold mb-4">Customer Service</h4>
<ul class="space-y-2 text-gray-400">
<li><a href="contact.php" class="hover:text-white">Contact Us</a></li>
<li><a href="shipping-info.php" class="hover:text-white">Shipping Info</a></li>
<li><a href="returns.php" class="hover:text-white">Returns</a></li>
<li><a href="faq.php" class="hover:text-white">FAQ</a></li>
</ul>
</div>
<div>
<h4 class="font-semibold mb-4">For Merchants</h4>
<ul class="space-y-2 text-gray-400">
<li><a href="merchant/register.php" class="hover:text-white">Become a Seller</a></li>
<li><a href="merchant/login.php" class="hover:text-white">Merchant Login</a></li>
<li><a href="seller-guide.php" class="hover:text-white">Seller Guide</a></li>
</ul>
</div>
<div>
<h4 class="font-semibold mb-4">Connect</h4>
<div class="flex space-x-4">
<a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook text-xl"></i></a>
<a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter text-xl"></i></a>
<a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram text-xl"></i></a>
</div>
</div>
</div>
<div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
<p>&copy; 2024 VentDepot. All rights reserved.</p>
</div>
</div>
</footer>

<script>
    function addToCart(productId) {
        // Use fetch to call the backend API
        fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'add',
                product_id: productId,
                quantity: 1
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Product added to cart!');
                // We reload to update the cart count in header relative to session data
                location.reload(); 
            } else {
                alert('Failed to add to cart: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error adding to cart. Make sure you are logged in or server is reachable.');
        });
    }
</script>

</body>
</html>