<?php
require_once '../config/database.php';
require_once '../classes/GlobalShippingCalculator.php';

// Require admin login
requireRole('admin');

$globalShipping = new GlobalShippingCalculator($pdo);
$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_rate_rule') {
        $ruleId = intval($_POST['rule_id'] ?? 0);
        $baseCost = floatval($_POST['base_cost'] ?? 0);
        $costPerKg = floatval($_POST['cost_per_kg'] ?? 0);
        $costPerKm = floatval($_POST['cost_per_km'] ?? 0);
        $costPerCm3 = floatval($_POST['cost_per_cm3'] ?? 0);
        $insuranceRate = floatval($_POST['insurance_rate'] ?? 0);
        $fuelSurcharge = floatval($_POST['fuel_surcharge'] ?? 0);
        $customsFee = floatval($_POST['customs_fee'] ?? 0);
        $freeThreshold = floatval($_POST['free_threshold'] ?? 0) ?: null;
        
        if ($ruleId && $baseCost > 0) {
            $stmt = $pdo->prepare("
                UPDATE shipping_rate_rules 
                SET base_cost = ?, cost_per_kg = ?, cost_per_km = ?, cost_per_cm3 = ?,
                    insurance_rate = ?, fuel_surcharge_rate = ?, customs_fee = ?, 
                    free_shipping_threshold = ?, updated_at = NOW()
                WHERE id = ?
            ");
            if ($stmt->execute([$baseCost, $costPerKg, $costPerKm, $costPerCm3, $insuranceRate, $fuelSurcharge, $customsFee, $freeThreshold, $ruleId])) {
                $success = 'Shipping rate updated successfully!';
            } else {
                $error = 'Failed to update shipping rate.';
            }
        }
    } elseif ($action === 'update_shipment_status') {
        $shipmentId = intval($_POST['shipment_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        
        if ($shipmentId && $status) {
            if ($globalShipping->updateShippingStatus($shipmentId, $status, $description, $location)) {
                $success = 'Shipment status updated successfully!';
            } else {
                $error = 'Failed to update shipment status.';
            }
        }
    } elseif ($action === 'add_shipping_provider') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $trackingUrl = trim($_POST['tracking_url'] ?? '');
        $maxWeight = floatval($_POST['max_weight'] ?? 0);
        $supportsIntl = isset($_POST['supports_international']);
        $supportsInsurance = isset($_POST['supports_insurance']);
        
        if ($name && $code) {
            $stmt = $pdo->prepare("
                INSERT INTO shipping_providers (name, code, tracking_url_template, max_weight_kg, supports_international, supports_insurance)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$name, $code, $trackingUrl, $maxWeight, $supportsIntl, $supportsInsurance])) {
                $success = 'Shipping provider added successfully!';
            } else {
                $error = 'Failed to add shipping provider.';
            }
        }
    }
}

// Get data for display
$providers = $pdo->query("
    SELECT sp.*, COUNT(ss.id) as service_count 
    FROM shipping_providers sp 
    LEFT JOIN shipping_services ss ON sp.id = ss.provider_id 
    GROUP BY sp.id 
    ORDER BY sp.name
")->fetchAll();

$shippingTypes = $pdo->query("SELECT * FROM shipping_types ORDER BY name")->fetchAll();
$packageTypes = $pdo->query("SELECT * FROM package_types ORDER BY name")->fetchAll();
$insuranceOptions = $pdo->query("SELECT * FROM shipping_insurance ORDER BY rate_percentage")->fetchAll();

// Get rate rules with details
$rateRules = $pdo->query("
    SELECT srr.*, sp.name as provider_name, ss.name as service_name, 
           sz.name as zone_name, st.name as shipping_type_name
    FROM shipping_rate_rules srr
    JOIN shipping_providers sp ON srr.provider_id = sp.id
    JOIN shipping_services ss ON srr.service_id = ss.id
    JOIN shipping_zones sz ON srr.zone_id = sz.id
    JOIN shipping_types st ON srr.shipping_type_id = st.id
    ORDER BY sp.name, ss.name, sz.name
    LIMIT 50
")->fetchAll();

// Get recent shipments
$recentShipments = $pdo->query("
    SELECT s.*, sp.name as provider_name, ss.name as service_name,
           o.id as order_number, u.email as customer_email
    FROM shipments s
    JOIN shipping_providers sp ON s.provider_id = sp.id
    JOIN shipping_services ss ON s.service_id = ss.id
    JOIN orders o ON s.order_id = o.id
    JOIN users u ON o.user_id = u.id
    ORDER BY s.created_at DESC
    LIMIT 20
")->fetchAll();

// Get global statistics
$stats = [
    'total_countries' => $pdo->query("SELECT COUNT(*) FROM countries WHERE shipping_allowed = TRUE")->fetchColumn(),
    'total_providers' => $pdo->query("SELECT COUNT(*) FROM shipping_providers WHERE is_active = TRUE")->fetchColumn(),
    'total_services' => $pdo->query("SELECT COUNT(*) FROM shipping_services WHERE is_active = TRUE")->fetchColumn(),
    'active_shipments' => $pdo->query("SELECT COUNT(*) FROM shipments WHERE status NOT IN ('delivered', 'returned', 'cancelled')")->fetchColumn(),
    'total_shipments' => $pdo->query("SELECT COUNT(*) FROM shipments")->fetchColumn()
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Global Shipping Administration - VentDepot</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">Global Shipping Administration</h1>
                            <p class="text-gray-600 mt-2">Manage worldwide shipping providers, rates, and tracking</p>
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

                    <!-- Statistics Dashboard -->
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-globe text-blue-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Countries</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_countries'] ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-truck text-green-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Providers</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_providers'] ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-shipping-fast text-purple-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Services</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_services'] ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-box text-orange-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Active Shipments</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['active_shipments'] ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-chart-line text-red-600 text-2xl"></i>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-500">Total Shipments</p>
                                    <p class="text-2xl font-semibold text-gray-900"><?= $stats['total_shipments'] ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div class="bg-white rounded-lg shadow-md overflow-hidden" x-data="{ activeTab: 'providers' }">
                        <!-- Tab Navigation -->
                        <div class="border-b border-gray-200">
                            <nav class="flex space-x-8 px-6 overflow-x-auto">
                                <button @click="activeTab = 'providers'" 
                                        :class="activeTab === 'providers' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                                    <i class="fas fa-truck mr-2"></i>Providers
                                </button>
                                <button @click="activeTab = 'rates'" 
                                        :class="activeTab === 'rates' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                                    <i class="fas fa-calculator mr-2"></i>Rate Management
                                </button>
                                <button @click="activeTab = 'shipments'" 
                                        :class="activeTab === 'shipments' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                                    <i class="fas fa-box mr-2"></i>Shipment Tracking
                                </button>
                                <button @click="activeTab = 'types'" 
                                        :class="activeTab === 'types' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                                    <i class="fas fa-tags mr-2"></i>Types & Insurance
                                </button>
                                <button @click="activeTab = 'calculator'" 
                                        :class="activeTab === 'calculator' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'"
                                        class="py-4 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                                    <i class="fas fa-globe mr-2"></i>Global Calculator
                                </button>
                            </nav>
                        </div>

                        <!-- Tab Content -->
                        <div class="p-6">
                            <!-- Providers Tab -->
                            <div x-show="activeTab === 'providers'" class="space-y-6">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-semibold text-gray-900">Global Shipping Providers</h3>
                                    <button onclick="document.getElementById('addProviderModal').classList.remove('hidden')"
                                            class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                        <i class="fas fa-plus mr-2"></i>Add Provider
                                    </button>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <?php foreach ($providers as $provider): ?>
                                        <div class="border border-gray-200 rounded-lg p-4">
                                            <div class="flex items-center justify-between mb-3">
                                                <h4 class="font-medium text-gray-900"><?= htmlspecialchars($provider['name']) ?></h4>
                                                <span class="text-xs bg-gray-100 text-gray-800 px-2 py-1 rounded">
                                                    <?= htmlspecialchars($provider['code']) ?>
                                                </span>
                                            </div>
                                            
                                            <div class="space-y-2 text-sm text-gray-600">
                                                <div class="flex justify-between">
                                                    <span>Max Weight:</span>
                                                    <span><?= $provider['max_weight_kg'] ?>kg</span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span>Services:</span>
                                                    <span><?= $provider['service_count'] ?></span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span>International:</span>
                                                    <span class="<?= $provider['supports_international'] ? 'text-green-600' : 'text-red-600' ?>">
                                                        <?= $provider['supports_international'] ? 'Yes' : 'No' ?>
                                                    </span>
                                                </div>
                                                <div class="flex justify-between">
                                                    <span>Insurance:</span>
                                                    <span class="<?= $provider['supports_insurance'] ? 'text-green-600' : 'text-red-600' ?>">
                                                        <?= $provider['supports_insurance'] ? 'Yes' : 'No' ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-4 pt-3 border-t border-gray-200">
                                                <div class="flex justify-between items-center">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        <?= $provider['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                        <?= $provider['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                    <button onclick="editProvider(<?= $provider['id'] ?>)" class="text-blue-600 hover:text-blue-800 text-sm">
                                                        <i class="fas fa-edit mr-1"></i>Edit
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Rate Management Tab -->
                            <div x-show="activeTab === 'rates'" class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900">Shipping Rate Rules</h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider/Service</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Zone</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Base Cost</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Per KG</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Per KM</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach (array_slice($rateRules, 0, 10) as $rule): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($rule['provider_name']) ?></div>
                                                        <div class="text-sm text-gray-500"><?= htmlspecialchars($rule['service_name']) ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= htmlspecialchars($rule['zone_name']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        $<?= number_format($rule['base_cost'], 2) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        $<?= number_format($rule['cost_per_kg'], 2) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        $<?= number_format($rule['cost_per_km'], 4) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button onclick="editRate(<?= $rule['id'] ?>)" 
                                                                class="text-blue-600 hover:text-blue-900">
                                                            <i class="fas fa-edit mr-1"></i>Edit
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Shipment Tracking Tab -->
                            <div x-show="activeTab === 'shipments'" class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900">Recent Shipments</h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tracking #</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provider</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach (array_slice($recentShipments, 0, 10) as $shipment): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                        <?= htmlspecialchars($shipment['tracking_number']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        #<?= str_pad($shipment['order_number'], 6, '0', STR_PAD_LEFT) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= htmlspecialchars($shipment['customer_email']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= htmlspecialchars($shipment['provider_name']) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                            <?php
                                                            switch($shipment['status']) {
                                                                case 'created': echo 'bg-gray-100 text-gray-800'; break;
                                                                case 'picked_up': echo 'bg-blue-100 text-blue-800'; break;
                                                                case 'in_transit': echo 'bg-yellow-100 text-yellow-800'; break;
                                                                case 'delivered': echo 'bg-green-100 text-green-800'; break;
                                                                case 'exception': echo 'bg-red-100 text-red-800'; break;
                                                                default: echo 'bg-gray-100 text-gray-800';
                                                            }
                                                            ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $shipment['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <button onclick="updateStatus(<?= $shipment['id'] ?>)" 
                                                                class="text-blue-600 hover:text-blue-900">
                                                            <i class="fas fa-edit mr-1"></i>Update
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Types & Insurance Tab -->
                            <div x-show="activeTab === 'types'" class="space-y-6">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <!-- Shipping Types -->
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Shipping Types</h3>
                                        <div class="space-y-3">
                                            <?php foreach ($shippingTypes as $type): ?>
                                                <div class="border border-gray-200 rounded-lg p-4">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($type['name']) ?></h4>
                                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($type['description']) ?></p>
                                                            <div class="mt-2 text-xs text-gray-500">
                                                                Multiplier: <?= $type['base_multiplier'] ?>x | Max Weight: <?= $type['max_weight_kg'] ?>kg
                                                            </div>
                                                        </div>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                            <?= $type['is_active'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                                            <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>

                                    <!-- Insurance Options -->
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Insurance Options</h3>
                                        <div class="space-y-3">
                                            <?php foreach ($insuranceOptions as $insurance): ?>
                                                <div class="border border-gray-200 rounded-lg p-4">
                                                    <div class="flex justify-between items-start">
                                                        <div>
                                                            <h4 class="font-medium text-gray-900"><?= htmlspecialchars($insurance['name']) ?></h4>
                                                            <p class="text-sm text-gray-600"><?= htmlspecialchars($insurance['description']) ?></p>
                                                            <div class="mt-2 text-xs text-gray-500">
                                                                Rate: <?= ($insurance['rate_percentage'] * 100) ?>% | 
                                                                Max Coverage: $<?= number_format($insurance['max_coverage_amount']) ?>
                                                            </div>
                                                        </div>
                                                        <span class="text-sm font-medium text-blue-600">
                                                            <?= ucfirst($insurance['coverage_type']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Global Calculator Tab -->
                            <div x-show="activeTab === 'calculator'" class="space-y-6">
                                <h3 class="text-lg font-semibold text-gray-900">Global Shipping Calculator Test</h3>
                                
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-blue-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-blue-800">Global Shipping Features</h3>
                                            <div class="mt-2 text-sm text-blue-700">
                                                <ul class="list-disc list-inside space-y-1">
                                                    <li><strong>50+ Countries</strong> with accurate coordinates and tax rates</li>
                                                    <li><strong>Distance-based Pricing</strong> calculated from California base</li>
                                                    <li><strong>Weight × Volume × Distance</strong> cost calculation</li>
                                                    <li><strong>Insurance Options</strong> with coverage up to $100,000</li>
                                                    <li><strong>Special Handling</strong> for fragile, hazardous, liquid, perishable items</li>
                                                    <li><strong>Real-time Tracking</strong> with GPS coordinates</li>
                                                    <li><strong>Customs Integration</strong> with automatic fees</li>
                                                    <li><strong>Multi-currency Support</strong> with live exchange rates</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                                        <h4 class="font-medium text-gray-900 mb-3">Coverage Statistics</h4>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span>Countries:</span>
                                                <span class="font-medium"><?= $stats['total_countries'] ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Shipping Zones:</span>
                                                <span class="font-medium">6</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Package Types:</span>
                                                <span class="font-medium"><?= count($packageTypes) ?></span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Insurance Tiers:</span>
                                                <span class="font-medium"><?= count($insuranceOptions) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                                        <h4 class="font-medium text-gray-900 mb-3">Base Location</h4>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span>Origin:</span>
                                                <span class="font-medium">California, USA</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Coordinates:</span>
                                                <span class="font-medium">34.0522°N, 118.2437°W</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Timezone:</span>
                                                <span class="font-medium">PST/PDT</span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white border border-gray-200 rounded-lg p-6">
                                        <h4 class="font-medium text-gray-900 mb-3">Rate Factors</h4>
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span>Base Cost:</span>
                                                <span class="font-medium">$2.99 - $45.99</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Weight Factor:</span>
                                                <span class="font-medium">$1.00 - $8.00/kg</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Distance Factor:</span>
                                                <span class="font-medium">$0.0001 - $0.01/km</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span>Insurance:</span>
                                                <span class="font-medium">0.5% - 1.5%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Provider Modal -->
    <div id="addProviderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Add Shipping Provider</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_shipping_provider">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Provider Name</label>
                            <input type="text" name="name" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Provider Code</label>
                            <input type="text" name="code" required
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Tracking URL Template</label>
                            <input type="url" name="tracking_url"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                                   placeholder="https://example.com/track/{tracking_number}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Weight (kg)</label>
                            <input type="number" name="max_weight" step="0.1"
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <input type="checkbox" name="supports_international" id="supports_international"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="supports_international" class="ml-2 text-sm text-gray-700">
                                    Supports International Shipping
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="supports_insurance" id="supports_insurance"
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="supports_insurance" class="ml-2 text-sm text-gray-700">
                                    Supports Insurance
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="document.getElementById('addProviderModal').classList.add('hidden')"
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit"
                                class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            Add Provider
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function editRate(ruleId) {
            // Implementation for editing rate rules
            alert('Edit rate rule #' + ruleId + ' - Feature coming soon!');
        }
        
        function updateStatus(shipmentId) {
            // Implementation for updating shipment status
            alert('Update shipment #' + shipmentId + ' - Feature coming soon!');
        }
        
        function editProvider(providerId) {
            // Implementation for editing providers
            alert('Edit provider #' + providerId + ' - Feature coming soon!');
        }
    </script>
</body>
</html>
