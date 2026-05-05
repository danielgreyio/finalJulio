<?php
// Commission Tracking
require_once '../config/database.php';

// Require admin login
requireRole('admin');

// Check if user is authenticated and is admin
if (!isLoggedIn() || getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commission Tracking - VentDepot Admin</title>
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
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-3xl font-bold text-gray-800">Commission Tracking</h1>
                        <button onclick="openAddCommissionModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Commission
                        </button>
                    </div>

                    <!-- Commission Summary -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Total Commissions</h3>
                            <p class="text-3xl font-bold text-gray-800" id="totalCommissions">$0.00</p>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Pending</h3>
                            <p class="text-3xl font-bold text-yellow-600" id="pendingCommissions">$0.00</p>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Approved</h3>
                            <p class="text-3xl font-bold text-blue-600" id="approvedCommissions">$0.00</p>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Paid</h3>
                            <p class="text-3xl font-bold text-green-600" id="paidCommissions">$0.00</p>
                        </div>
                    </div>

                    <!-- Commission Tiers -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Commission Tiers</h2>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tier</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Min Sales Threshold</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="commissionTiersTable">
                                        <tr>
                                            <td colspan="3" class="px-6 py-4 text-center text-gray-500">Loading tiers...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Commissions Table -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Sales Commissions</h2>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Salesperson</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sales</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rate</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Commission</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tier</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="commissionsTable">
                                        <tr>
                                            <td colspan="8" class="px-6 py-4 text-center text-gray-500">Loading commissions...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Commission Modal -->
    <div id="addCommissionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Add Sales Commission</h3>
            </div>
            <div class="p-6">
                <form id="addCommissionForm">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="salesperson_id">Salesperson ID</label>
                        <input type="number" id="salesperson_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="salesperson_name">Salesperson Name</label>
                        <input type="text" id="salesperson_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="period_start">Period Start</label>
                            <input type="date" id="period_start" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="period_end">Period End</label>
                            <input type="date" id="period_end" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="total_sales">Total Sales</label>
                        <input type="number" id="total_sales" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="commission_rate">Commission Rate</label>
                            <input type="number" id="commission_rate" step="0.0001" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" value="0.0500">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="commission_amount">Commission Amount</label>
                            <input type="number" id="commission_amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" readonly>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="tier_level">Tier Level</label>
                        <select id="tier_level" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="bronze">Bronze</option>
                            <option value="silver">Silver</option>
                            <option value="gold">Gold</option>
                            <option value="platinum">Platinum</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddCommissionModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">Add Commission</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        loadCommissions();
        loadCommissionTiers();
        loadCommissionSummary();
        
        // Set default dates
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('period_start').valueAsDate = firstDay;
        document.getElementById('period_end').valueAsDate = today;
        
        // Handle form submissions
        document.getElementById('addCommissionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            addSalesCommission();
        });
        
        // Auto-calculate commission amount
        document.getElementById('total_sales').addEventListener('input', calculateCommissionAmount);
        document.getElementById('commission_rate').addEventListener('input', calculateCommissionAmount);
    });

    // Calculate commission amount
    function calculateCommissionAmount() {
        const totalSales = parseFloat(document.getElementById('total_sales').value) || 0;
        const commissionRate = parseFloat(document.getElementById('commission_rate').value) || 0;
        const commissionAmount = totalSales * commissionRate;
        document.getElementById('commission_amount').value = commissionAmount.toFixed(2);
    }

    // Load commissions
    function loadCommissions() {
        fetch('api/accounting-api.php?action=get_sales_commissions')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('commissionsTable');
                tbody.innerHTML = '';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(commission => {
                        const row = document.createElement('tr');
                        const statusClass = getStatusClass(commission.status);
                        
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${commission.salesperson_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${commission.period_start} to ${commission.period_end}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$${parseFloat(commission.total_sales).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${(parseFloat(commission.commission_rate) * 100).toFixed(2)}%</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$${parseFloat(commission.commission_amount).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">${commission.tier_level}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                    ${commission.status.charAt(0).toUpperCase() + commission.status.slice(1)}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                ${commission.status === 'pending' ? 
                                    `<button onclick="approveCommission(${commission.id})" class="text-green-600 hover:text-green-900 mr-3">Approve</button>` : 
                                    ''
                                }
                                ${commission.status === 'approved' ? 
                                    `<button onclick="payCommission(${commission.id})" class="text-blue-600 hover:text-blue-900 mr-3">Pay</button>` : 
                                    ''
                                }
                                <button class="text-gray-600 hover:text-gray-900">View</button>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">No commissions found</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading commissions:', error);
                document.getElementById('commissionsTable').innerHTML = '<tr><td colspan="8" class="px-6 py-4 text-center text-gray-500">Error loading commissions</td></tr>';
            });
    }

    // Load commission tiers
    function loadCommissionTiers() {
        fetch('api/accounting-api.php?action=get_commission_tiers')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('commissionTiersTable');
                tbody.innerHTML = '';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(tier => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 capitalize">${tier.tier_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">$${parseFloat(tier.min_sales_threshold).toFixed(2)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${(parseFloat(tier.commission_rate) * 100).toFixed(2)}%</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">No tiers found</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading commission tiers:', error);
                document.getElementById('commissionTiersTable').innerHTML = '<tr><td colspan="3" class="px-6 py-4 text-center text-gray-500">Error loading commission tiers</td></tr>';
            });
    }

    // Load commission summary
    function loadCommissionSummary() {
        fetch('api/accounting-api.php?action=get_sales_commissions')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.length > 0) {
                    let total = 0;
                    let pending = 0;
                    let approved = 0;
                    let paid = 0;
                    
                    data.data.forEach(commission => {
                        const amount = parseFloat(commission.commission_amount);
                        total += amount;
                        
                        if (commission.status === 'pending') {
                            pending += amount;
                        } else if (commission.status === 'approved') {
                            approved += amount;
                        } else if (commission.status === 'paid') {
                            paid += amount;
                        }
                    });
                    
                    document.getElementById('totalCommissions').textContent = '$' + total.toFixed(2);
                    document.getElementById('pendingCommissions').textContent = '$' + pending.toFixed(2);
                    document.getElementById('approvedCommissions').textContent = '$' + approved.toFixed(2);
                    document.getElementById('paidCommissions').textContent = '$' + paid.toFixed(2);
                }
            })
            .catch(error => {
                console.error('Error loading commission summary:', error);
            });
    }

    // Get status class for styling
    function getStatusClass(status) {
        switch(status) {
            case 'paid':
                return 'bg-green-100 text-green-800';
            case 'approved':
                return 'bg-blue-100 text-blue-800';
            case 'pending':
                return 'bg-yellow-100 text-yellow-800';
            case 'cancelled':
                return 'bg-gray-100 text-gray-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }

    // Open add commission modal
    function openAddCommissionModal() {
        document.getElementById('addCommissionModal').classList.remove('hidden');
        document.getElementById('addCommissionModal').classList.add('flex');
    }

    // Close add commission modal
    function closeAddCommissionModal() {
        document.getElementById('addCommissionModal').classList.add('hidden');
        document.getElementById('addCommissionModal').classList.remove('flex');
        document.getElementById('addCommissionForm').reset();
    }

    // Add sales commission
    function addSalesCommission() {
        const formData = {
            salesperson_id: document.getElementById('salesperson_id').value,
            salesperson_name: document.getElementById('salesperson_name').value,
            period_start: document.getElementById('period_start').value,
            period_end: document.getElementById('period_end').value,
            total_sales: document.getElementById('total_sales').value,
            commission_rate: document.getElementById('commission_rate').value,
            commission_amount: document.getElementById('commission_amount').value,
            tier_level: document.getElementById('tier_level').value
        };
        
        fetch('api/accounting-api.php?action=add_sales_commission', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Sales commission added successfully!');
                closeAddCommissionModal();
                loadCommissions();
                loadCommissionSummary();
            } else {
                alert('Error adding sales commission: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error adding sales commission:', error);
            alert('Error adding sales commission. Please try again.');
        });
    }

    // Approve commission
    function approveCommission(commissionId) {
        // In a real implementation, this would update the commission status to 'approved'
        alert('Commission approved! In a real implementation, this would update the commission status.');
        loadCommissions();
        loadCommissionSummary();
    }

    // Pay commission
    function payCommission(commissionId) {
        // In a real implementation, this would update the commission status to 'paid'
        alert('Commission paid! In a real implementation, this would update the commission status.');
        loadCommissions();
        loadCommissionSummary();
    }
    </script>
</body>
</html>