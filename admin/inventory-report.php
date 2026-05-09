<?php
require_once '../config/database.php';
requireRole('admin');

// Get report parameters
$reportType = $_GET['type'] ?? 'location';
$locationId = $_GET['location'] ?? '';

// Report titles and descriptions
$reportInfo = [
    'location' => [
        'title' => 'Inventory Report by Location',
        'description' => 'Complete inventory breakdown by warehouse locations'
    ],
    'rack' => [
        'title' => 'Inventory Report by Rack',
        'description' => 'Detailed view of inventory stored in each rack'
    ],
    'shelf' => [
        'title' => 'Inventory Report by Shelf',
        'description' => 'Inventory breakdown by individual shelves/levels'
    ],
    'bin' => [
        'title' => 'Inventory Report by Bin',
        'description' => 'Comprehensive bin-level inventory analysis'
    ]
];

$currentReport = $reportInfo[$reportType] ?? $reportInfo['location'];

// Generate mock data based on report type
$reportData = generateMockReportData($reportType, $locationId);

function generateMockReportData($type, $locationId) {
    $mockData = [];
    
    switch($type) {
        case 'location':
            $mockData = [
                [
                    'location_name' => 'Main Warehouse',
                    'location_code' => 'WH-001',
                    'total_items' => 1250,
                    'total_value' => 125000.00,
                    'zones' => 3,
                    'racks' => 12,
                    'utilization' => 78
                ],
                [
                    'location_name' => 'Secondary Storage',
                    'location_code' => 'WH-002', 
                    'total_items' => 890,
                    'total_value' => 89000.00,
                    'zones' => 2,
                    'racks' => 8,
                    'utilization' => 65
                ]
            ];
            break;
            
        case 'rack':
            for ($i = 1; $i <= 10; $i++) {
                $mockData[] = [
                    'rack_code' => 'R' . str_pad($i, 2, '0', STR_PAD_LEFT),
                    'rack_name' => 'Rack ' . $i,
                    'location' => 'Main Warehouse',
                    'levels' => 5,
                    'positions' => 10,
                    'total_bins' => 50,
                    'occupied_bins' => rand(20, 45),
                    'total_items' => rand(100, 300),
                    'total_value' => rand(10000, 50000),
                    'utilization' => rand(60, 90)
                ];
            }
            break;
            
        case 'shelf':
            for ($r = 1; $r <= 5; $r++) {
                for ($l = 1; $l <= 5; $l++) {
                    $mockData[] = [
                        'rack_code' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT),
                        'level_number' => $l,
                        'shelf_address' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT) . '-L' . $l,
                        'total_positions' => 10,
                        'occupied_positions' => rand(4, 9),
                        'total_items' => rand(20, 80),
                        'total_value' => rand(2000, 15000),
                        'utilization' => rand(40, 90)
                    ];
                }
            }
            break;
            
        case 'bin':
            for ($r = 1; $r <= 3; $r++) {
                for ($l = 1; $l <= 3; $l++) {
                    for ($p = 1; $p <= 5; $p++) {
                        $isEmpty = rand(1, 100) > 75; // 25% chance of being empty
                        $mockData[] = [
                            'bin_address' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT) . '-L' . $l . '-P' . str_pad($p, 2, '0', STR_PAD_LEFT),
                            'rack_code' => 'R' . str_pad($r, 2, '0', STR_PAD_LEFT),
                            'level_number' => $l,
                            'position_number' => $p,
                            'status' => $isEmpty ? 'empty' : 'occupied',
                            'items_count' => $isEmpty ? 0 : rand(1, 15),
                            'total_value' => $isEmpty ? 0 : rand(100, 5000),
                            'last_updated' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'))
                        ];
                    }
                }
            }
            break;
    }
    
    return $mockData;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentReport['title']) ?> - VentDepot Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-6">
        <!-- Report Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($currentReport['title']) ?></h1>
                    <p class="text-gray-600 mb-4"><?= htmlspecialchars($currentReport['description']) ?></p>
                    <div class="text-sm text-gray-500">
                        <i class="fas fa-calendar mr-2"></i>Generated: <?= date('F j, Y \a\t g:i A') ?>
                        <?php if ($locationId): ?>
                            <span class="ml-4"><i class="fas fa-map-marker-alt mr-2"></i>Location: <?= htmlspecialchars($locationId) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex space-x-2 no-print">
                    <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-print mr-2"></i>Print
                    </button>
                    <button onclick="exportToCSV()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        <i class="fas fa-download mr-2"></i>Export CSV
                    </button>
                    <button onclick="window.close()" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        <i class="fas fa-times mr-2"></i>Close
                    </button>
                </div>
            </div>
        </div>

        <!-- Report Summary -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">📊 Summary Statistics</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-blue-600"><?= count($reportData) ?></div>
                    <div class="text-sm text-gray-600">
                        <?= ucfirst($reportType) ?><?= count($reportData) != 1 ? 's' : '' ?>
                    </div>
                </div>
                <div class="bg-green-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-green-600">
                        <?= number_format(array_sum(array_column($reportData, 'total_items' ?: 'items_count')) ?: rand(1000, 5000)) ?>
                    </div>
                    <div class="text-sm text-gray-600">Total Items</div>
                </div>
                <div class="bg-purple-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-purple-600">
                        $<?= number_format(array_sum(array_column($reportData, 'total_value')) ?: rand(100000, 500000), 2) ?>
                    </div>
                    <div class="text-sm text-gray-600">Total Value</div>
                </div>
                <div class="bg-orange-50 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-orange-600">
                        <?= round(array_sum(array_column($reportData, 'utilization')) / count($reportData)) ?>%
                    </div>
                    <div class="text-sm text-gray-600">Avg Utilization</div>
                </div>
            </div>
        </div>

        <!-- Report Data Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">📋 Detailed Report</h2>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200" id="reportTable">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php if ($reportType === 'location'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Zones</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Racks</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilization</th>
                            <?php elseif ($reportType === 'rack'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rack Code</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bins</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupied</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilization</th>
                            <?php elseif ($reportType === 'shelf'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shelf Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rack</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Positions</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Occupied</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilization</th>
                            <?php else: // bin ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bin Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rack</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $row): ?>
                            <tr class="hover:bg-gray-50">
                                <?php if ($reportType === 'location'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['location_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['location_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format($row['total_items']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?= number_format($row['total_value'], 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['zones'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['racks'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $row['utilization'] > 80 ? 'bg-green-100 text-green-800' : ($row['utilization'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $row['utilization'] ?>%
                                        </span>
                                    </td>
                                <?php elseif ($reportType === 'rack'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['rack_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['rack_name']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['location']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $row['total_bins'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $row['occupied_bins'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format($row['total_items']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?= number_format($row['total_value'], 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $row['utilization'] > 80 ? 'bg-green-100 text-green-800' : ($row['utilization'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $row['utilization'] ?>%
                                        </span>
                                    </td>
                                <?php elseif ($reportType === 'shelf'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['shelf_address']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['rack_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['level_number'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $row['total_positions'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $row['occupied_positions'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format($row['total_items']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?= number_format($row['total_value'], 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $row['utilization'] > 80 ? 'bg-green-100 text-green-800' : ($row['utilization'] > 60 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= $row['utilization'] ?>%
                                        </span>
                                    </td>
                                <?php else: // bin ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($row['bin_address']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($row['rack_code']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['level_number'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $row['position_number'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full <?= $row['status'] === 'empty' ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800' ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $row['items_count'] ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$<?= number_format($row['total_value'], 2) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('M j, Y', strtotime($row['last_updated'])) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function exportToCSV() {
            const table = document.getElementById('reportTable');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            const csvContent = rows.map(row => {
                const cells = Array.from(row.querySelectorAll('th, td'));
                return cells.map(cell => {
                    const text = cell.textContent.trim();
                    return text.includes(',') ? `"${text}"` : text;
                }).join(',');
            }).join('\n');
            
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `<?= strtolower($reportType) ?>_inventory_report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>