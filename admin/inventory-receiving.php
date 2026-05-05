<?php
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin login
requireRole('admin');

$success = '';
$error = '';

// Handle inventory receiving actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'receive_inventory') {
        try {
            $productId = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $binId = $_POST['bin_id'];
            $binAddress = $_POST['bin_address'];
            $notes = $_POST['notes'] ?? '';
            
            $pdo->beginTransaction();
            
            // Get bin information and location
            $stmt = $pdo->prepare("
                SELECT ib.*, sr.zone_id, wz.location_id
                FROM inventory_bins ib
                JOIN storage_racks sr ON ib.rack_id = sr.id
                JOIN warehouse_zones wz ON sr.zone_id = wz.id
                WHERE ib.bin_address = ?
            ");
            $stmt->execute([$binAddress]);
            $binInfo = $stmt->fetch();
            
            if (!$binInfo) {
                throw new Exception('Bin not found');
            }
            
            // Update bin with inventory
            $stmt = $pdo->prepare("
                UPDATE inventory_bins 
                SET occupancy_status = 'occupied', 
                    current_quantity = COALESCE(current_quantity, 0) + ?,
                    last_updated = NOW()
                WHERE bin_address = ?
            ");
            $stmt->execute([$quantity, $binAddress]);
            
            // Update or create product inventory record
            $stmt = $pdo->prepare("
                SELECT * FROM product_inventory 
                WHERE product_id = ? AND location_id = ?
            ");
            $stmt->execute([$productId, $binInfo['location_id']]);
            $existingInventory = $stmt->fetch();
            
            if ($existingInventory) {
                // Update existing inventory
                $stmt = $pdo->prepare("
                    UPDATE product_inventory 
                    SET quantity_on_hand = quantity_on_hand + ?,
                        last_movement_at = NOW()
                    WHERE product_id = ? AND location_id = ?
                ");
                $stmt->execute([$quantity, $productId, $binInfo['location_id']]);
            } else {
                // Create new inventory record
                $stmt = $pdo->prepare("
                    INSERT INTO product_inventory 
                    (product_id, location_id, quantity_on_hand, quantity_reserved, reorder_point, max_stock_level)
                    VALUES (?, ?, ?, 0, 10, 100)
                ");
                $stmt->execute([$productId, $binInfo['location_id'], $quantity]);
            }
            
            // Create inventory movement record
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO inventory_movements 
                    (product_id, location_id, movement_type, quantity, reference_type, notes, created_at, created_by) 
                    VALUES (?, ?, 'in', ?, 'receiving', ?, NOW(), ?)
                ");
                $stmt->execute([$productId, $binInfo['location_id'], $quantity, $notes, $_SESSION['user_id'] ?? 1]);
            } catch (PDOException $e) {
                // Inventory movements table might not exist, continue without error
            }
            
            // Update product stock total
            $stmt = $pdo->prepare("
                UPDATE products 
                SET inventory = (SELECT COALESCE(SUM(quantity_on_hand), 0) FROM product_inventory WHERE product_id = ?)
                WHERE id = ?
            ");
            $stmt->execute([$productId, $productId]);
            
            // Create bin assignment record
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO bin_assignments
                    (bin_id, product_id, quantity, assigned_at, status)
                    VALUES (?, ?, ?, NOW(), 'active')
                    ON DUPLICATE KEY UPDATE
                    quantity = quantity + VALUES(quantity),
                    assigned_at = NOW()
                ");
                $stmt->execute([$binInfo['id'], $productId, $quantity]);
            } catch (PDOException $e) {
                // Bin assignments table might not exist, continue without error
            }
            
            $pdo->commit();
            $success = "Inventory received successfully! {$quantity} units placed in bin {$binAddress}";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error receiving inventory: " . $e->getMessage();
        }
    }
}

// Get all products for selection
try {
    $stmt = $pdo->prepare("SELECT id, name, sku, price FROM products WHERE status = 'active' ORDER BY name");
    $stmt->execute();
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}

// Get all locations for filtering
try {
    $stmt = $pdo->prepare("SELECT id, location_name, location_code FROM inventory_locations WHERE status = 'active' ORDER BY location_name");
    $stmt->execute();
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    $locations = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Receiving - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50" x-data="inventoryReceiving()">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center space-x-4">
                    <a href="dashboard.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">📦 Inventory Receiving</h1>
                        <p class="text-sm text-gray-600">Find empty locations and register new inventory</p>
                    </div>
                </div>
                <button @click="showReceivingModal = true" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                    <i class="fas fa-plus mr-2"></i>Receive Inventory
                </button>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Filter Controls -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
            <div class="flex flex-wrap items-center gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <select x-model="selectedLocation" @change="loadEmptySpaces()" class="border border-gray-300 rounded-md px-3 py-2">
                        <option value="">All Locations</option>
                        <template x-for="location in locations" :key="location.id">
                            <option :value="location.id" x-text="location.location_name + ' (' + location.location_code + ')'"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Show</label>
                    <select x-model="filterType" @change="filterEmptySpaces()" class="border border-gray-300 rounded-md px-3 py-2">
                        <option value="all">All Empty Locations</option>
                        <option value="bins">Empty Bins Only</option>
                        <option value="shelves">Empty Shelves</option>
                        <option value="racks">Empty Racks</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Capacity</label>
                    <select x-model="capacityFilter" @change="filterEmptySpaces()" class="border border-gray-300 rounded-md px-3 py-2">
                        <option value="">Any Capacity</option>
                        <option value="small">Small Items</option>
                        <option value="medium">Medium Items</option>
                        <option value="large">Large Items</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button @click="loadEmptySpaces()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-search mr-2"></i>Search
                    </button>
                </div>
            </div>
        </div>

        <!-- Empty Spaces Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100">
                        <i class="fas fa-warehouse text-blue-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Empty Locations</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="emptyStats.locations || 0"></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100">
                        <i class="fas fa-th-large text-green-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Empty Racks</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="emptyStats.racks || 0"></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100">
                        <i class="fas fa-layer-group text-purple-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Empty Shelves</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="emptyStats.shelves || 0"></p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100">
                        <i class="fas fa-cube text-yellow-600 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Empty Bins</p>
                        <p class="text-2xl font-bold text-gray-900" x-text="emptyStats.bins || 0"></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty Spaces List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <!-- Loading State -->
            <div x-show="loading" class="p-8 text-center">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                <p class="mt-2 text-gray-600">Loading empty spaces...</p>
            </div>
            
            <!-- Empty Spaces Table -->
            <div x-show="!loading" class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Address</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <template x-for="space in filteredSpaces" :key="space.id">
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-lg flex items-center justify-center"
                                                 :class="space.type === 'bin' ? 'bg-yellow-100' : 
                                                        space.type === 'shelf' ? 'bg-purple-100' : 
                                                        space.type === 'rack' ? 'bg-green-100' : 'bg-blue-100'">
                                                <i :class="space.type === 'bin' ? 'fas fa-cube text-yellow-600' : 
                                                          space.type === 'shelf' ? 'fas fa-layer-group text-purple-600' : 
                                                          space.type === 'rack' ? 'fas fa-th-large text-green-600' : 'fas fa-warehouse text-blue-600'"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900" x-text="space.location_name"></div>
                                            <div class="text-sm text-gray-500" x-text="space.location_code"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900" x-text="space.address"></div>
                                    <div class="text-sm text-gray-500" x-text="space.full_path"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                          :class="space.type === 'bin' ? 'bg-yellow-100 text-yellow-800' : 
                                                 space.type === 'shelf' ? 'bg-purple-100 text-purple-800' : 
                                                 space.type === 'rack' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800'"
                                          x-text="space.type.charAt(0).toUpperCase() + space.type.slice(1)">
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Empty
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <template x-if="space.type === 'bin'">
                                        <button @click="selectBinForReceiving(space)" 
                                                class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-plus mr-1"></i>Receive Here
                                        </button>
                                    </template>
                                    <button @click="viewSpaceDetails(space)" 
                                            class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </button>
                                </td>
                            </tr>
                        </template>
                        
                        <template x-if="filteredSpaces.length === 0 && !loading">
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                                    <p class="text-lg font-medium">No Empty Spaces Found</p>
                                    <p class="text-sm">All selected locations are currently occupied</p>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Receiving Modal -->
    <div x-show="showReceivingModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50" x-cloak>
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="receive_inventory">
                    <input type="hidden" name="bin_id" x-model="selectedBin.bin_id">
                    <input type="hidden" name="bin_address" x-model="selectedBin.bin_address">
                    <?= Security::getCSRFInput() ?>
                    
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-semibold text-gray-900">📦 Receive Inventory</h3>
                        <button type="button" @click="showReceivingModal = false" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Product</label>
                            <select name="product_id" required class="w-full border border-gray-300 rounded-md px-3 py-2">
                                <option value="">Select Product</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>">
                                        <?= htmlspecialchars($product['name']) ?> 
                                        <?php if ($product['sku']): ?>(<?= htmlspecialchars($product['sku']) ?>)<?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantity</label>
                            <input type="number" name="quantity" min="1" required 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Target Location</label>
                            <input type="text" :value="selectedBin.bin_address || 'Select from empty spaces below'" readonly 
                                   class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Notes (Optional)</label>
                            <textarea name="notes" rows="3" 
                                      class="w-full border border-gray-300 rounded-md px-3 py-2"></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" @click="showReceivingModal = false" 
                                class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                            Cancel
                        </button>
                        <button type="submit" :disabled="!selectedBin.bin_address"
                                class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 disabled:bg-gray-400">
                            <i class="fas fa-check mr-2"></i>Receive Inventory
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function inventoryReceiving() {
            return {
                loading: false,
                showReceivingModal: false,
                selectedLocation: '',
                filterType: 'all',
                capacityFilter: '',
                locations: [],
                emptySpaces: [],
                filteredSpaces: [],
                selectedBin: {
                    bin_id: '',
                    bin_address: ''
                },
                emptyStats: {
                    locations: 0,
                    racks: 0,
                    shelves: 0,
                    bins: 0
                },
                
                init() {
                    this.loadLocations();
                    this.loadEmptySpaces();
                },
                
                async loadLocations() {
                    try {
                        // Load real locations from PHP
                        this.locations = <?= json_encode($locations) ?>;
                    } catch (error) {
                        console.error('Error loading locations:', error);
                        this.locations = [];
                    }
                },
                
                async loadEmptySpaces() {
                    this.loading = true;
                    try {
                        // Load empty spaces from database
                        const response = await fetch('ajax/get-empty-spaces.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                location_id: this.selectedLocation || null
                            })
                        });
                        
                        if (response.ok) {
                            const data = await response.json();
                            this.emptySpaces = data.spaces || [];
                            if (data.stats) {
                                this.emptyStats = data.stats;
                            }
                        } else {
                            // Fallback to mock data if AJAX fails
                            this.emptySpaces = this.generateMockEmptySpaces();
                            this.updateEmptyStats();
                        }
                        
                        this.filterEmptySpaces();
                    } catch (error) {
                        console.error('Error loading empty spaces:', error);
                        // Fallback to mock data on error
                        this.emptySpaces = this.generateMockEmptySpaces();
                        this.updateEmptyStats();
                        this.filterEmptySpaces();
                    } finally {
                        this.loading = false;
                    }
                },
                
                generateMockEmptySpaces() {
                    const spaces = [];
                    
                    // Generate empty bins
                    for (let r = 1; r <= 10; r++) {
                        for (let l = 1; l <= 5; l++) {
                            for (let p = 1; p <= 10; p++) {
                                // 70% chance of being empty for demo
                                if (Math.random() > 0.3) {
                                    const binAddress = `R${String(r).padStart(2, '0')}-L${l}-P${String(p).padStart(2, '0')}`;
                                    spaces.push({
                                        id: `bin-${r}-${l}-${p}`,
                                        type: 'bin',
                                        location_name: 'Main Warehouse',
                                        location_code: 'WH-001',
                                        address: binAddress,
                                        full_path: `Main Warehouse → Zone A → Rack R${String(r).padStart(2, '0')} → Level ${l} → ${binAddress}`,
                                        bin_address: binAddress
                                    });
                                }
                            }
                        }
                    }
                    
                    return spaces;
                },
                
                updateEmptyStats() {
                    const stats = {
                        locations: new Set(),
                        racks: new Set(),
                        shelves: new Set(),
                        bins: 0
                    };
                    
                    this.emptySpaces.forEach(space => {
                        stats.locations.add(space.location_code);
                        if (space.type === 'bin') {
                            stats.bins++;
                            const rackMatch = space.address.match(/R(\d+)/);
                            const shelfMatch = space.address.match(/L(\d+)/);
                            if (rackMatch) stats.racks.add(`R${rackMatch[1]}`);
                            if (shelfMatch) stats.shelves.add(`${rackMatch ? rackMatch[0] : ''}-L${shelfMatch[1]}`);
                        }
                    });
                    
                    this.emptyStats = {
                        locations: stats.locations.size,
                        racks: stats.racks.size,
                        shelves: stats.shelves.size,
                        bins: stats.bins
                    };
                },
                
                filterEmptySpaces() {
                    let filtered = [...this.emptySpaces];
                    
                    if (this.selectedLocation) {
                        filtered = filtered.filter(space => space.location_id == this.selectedLocation);
                    }
                    
                    if (this.filterType !== 'all') {
                        filtered = filtered.filter(space => space.type === this.filterType.slice(0, -1));
                    }
                    
                    this.filteredSpaces = filtered;
                },
                
                selectBinForReceiving(space) {
                    this.selectedBin = {
                        bin_id: space.id,
                        bin_address: space.address
                    };
                    this.showReceivingModal = true;
                },
                
                viewSpaceDetails(space) {
                    alert(`Space Details:\nType: ${space.type}\nAddress: ${space.address}\nLocation: ${space.location_name}`);
                }
            };
        }
    </script>
</body>
</html>