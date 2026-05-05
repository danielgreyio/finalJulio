<?php
// Accounting Dashboard
require_once '../config/database.php';

// Require admin login
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard - VentDepot</title>
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
                        <h1 class="text-3xl font-bold text-gray-800">Accounting Dashboard</h1>
                        <button onclick="openAddTransactionModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Add Transaction
                        </button>
                    </div>

                    <!-- Financial Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Total Revenue -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="rounded-full bg-green-100 p-3">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-gray-500 text-sm font-medium">Total Revenue</h2>
                                    <p class="text-2xl font-bold text-gray-800" id="totalRevenue">$0.00</p>
                                </div>
                            </div>
                        </div>

                        <!-- Total Expenses -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="rounded-full bg-red-100 p-3">
                                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-gray-500 text-sm font-medium">Total Expenses</h2>
                                    <p class="text-2xl font-bold text-gray-800" id="totalExpenses">$0.00</p>
                                </div>
                            </div>
                        </div>

                        <!-- Accounts Receivable -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="rounded-full bg-blue-100 p-3">
                                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-gray-500 text-sm font-medium">Accounts Receivable</h2>
                                    <p class="text-2xl font-bold text-gray-800" id="accountsReceivable">$0.00</p>
                                </div>
                            </div>
                        </div>

                        <!-- Accounts Payable -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="rounded-full bg-yellow-100 p-3">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h2 class="text-gray-500 text-sm font-medium">Accounts Payable</h2>
                                    <p class="text-2xl font-bold text-gray-800" id="accountsPayable">$0.00</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Recent Transactions -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Recent Transactions</h2>
                            </div>
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200" id="recentTransactions">
                                            <tr>
                                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading transactions...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Accounts Overview -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h2 class="text-lg font-semibold text-gray-800">Chart of Accounts</h2>
                            </div>
                            <div class="p-6">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Account</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200" id="chartOfAccounts">
                                            <tr>
                                                <td colspan="4" class="px-6 py-4 text-center text-gray-500">Loading accounts...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Financial Reports Section -->
                    <div class="mt-8 bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Financial Reports</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <button onclick="generateReport('income_statement')" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-3 rounded-lg text-center">
                                    <div class="font-medium">Income Statement</div>
                                    <div class="text-sm opacity-90 mt-1">Revenue & Expenses</div>
                                </button>
                                <button onclick="generateReport('balance_sheet')" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-lg text-center">
                                    <div class="font-medium">Balance Sheet</div>
                                    <div class="text-sm opacity-90 mt-1">Assets & Liabilities</div>
                                </button>
                                <button onclick="generateReport('cash_flow')" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-3 rounded-lg text-center">
                                    <div class="font-medium">Cash Flow</div>
                                    <div class="text-sm opacity-90 mt-1">Cash Movements</div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- C-Level Financial Reporting -->
                    <div class="mt-8 bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">C-Level Financial Reporting</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                                <a href="c-level-dashboard.php" class="bg-indigo-100 hover:bg-indigo-200 text-indigo-800 p-4 rounded-lg text-center transition duration-200">
                                    <i class="fas fa-chart-bar text-2xl mb-2"></i>
                                    <div class="font-medium text-sm">Executive Dashboard</div>
                                </a>
                                <a href="cash-flow-forecasting.php" class="bg-blue-100 hover:bg-blue-200 text-blue-800 p-4 rounded-lg text-center transition duration-200">
                                    <i class="fas fa-chart-line text-2xl mb-2"></i>
                                    <div class="font-medium text-sm">Cash Flow</div>
                                </a>
                                <a href="budget-vs-actual.php" class="bg-green-100 hover:bg-green-200 text-green-800 p-4 rounded-lg text-center transition duration-200">
                                    <i class="fas fa-balance-scale text-2xl mb-2"></i>
                                    <div class="font-medium text-sm">Budget vs Actual</div>
                                </a>
                                <a href="unit-economics.php" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 p-4 rounded-lg text-center transition duration-200">
                                    <i class="fas fa-chart-pie text-2xl mb-2"></i>
                                    <div class="font-medium text-sm">Unit Economics</div>
                                </a>
                                <a href="growth-metrics.php" class="bg-purple-100 hover:bg-purple-200 text-purple-800 p-4 rounded-lg text-center transition duration-200">
                                    <i class="fas fa-arrow-up text-2xl mb-2"></i>
                                    <div class="font-medium text-sm">Growth Metrics</div>
                                </a>
                                <a href="risk-management.php" class="bg-red-100 hover:bg-red-200 text-red-800 p-4 rounded-lg text-center transition duration-200">
                                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                                    <div class="font-medium text-sm">Risk Management</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Transaction Modal -->
    <div id="addTransactionModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Add Journal Entry</h3>
            </div>
            <div class="p-6">
                <form id="addTransactionForm">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="account_id">Account</label>
                        <select id="account_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select an account</option>
                        </select>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="transaction_date">Date</label>
                        <input type="date" id="transaction_date" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="description">Description</label>
                        <input type="text" id="description" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="debit_amount">Debit Amount</label>
                            <input type="number" id="debit_amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="credit_amount">Credit Amount</label>
                            <input type="number" id="credit_amount" step="0.01" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="reference_type">Reference Type</label>
                        <select id="reference_type" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="manual">Manual Entry</option>
                            <option value="order">Order</option>
                            <option value="invoice">Invoice</option>
                            <option value="payment">Payment</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeAddTransactionModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-white bg-blue-600 rounded-md hover:bg-blue-700">Add Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Initialize dashboard
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardData();
        loadChartOfAccounts();
        loadRecentTransactions();
        
        // Set default date to today
        document.getElementById('transaction_date').valueAsDate = new Date();
        
        // Handle form submission
        document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            addJournalEntry();
        });
    });

    // Load dashboard summary data
    function loadDashboardData() {
        fetch('api/accounting-api.php?action=get_dashboard_summary')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const formatCurrency = (amount) => {
                        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
                    };
                    
                    document.getElementById('totalRevenue').textContent = formatCurrency(data.data.totalRevenue);
                    document.getElementById('totalExpenses').textContent = formatCurrency(data.data.totalExpenses);
                    document.getElementById('accountsReceivable').textContent = formatCurrency(data.data.accountsReceivable);
                    document.getElementById('accountsPayable').textContent = formatCurrency(data.data.accountsPayable);
                } else {
                    console.error('Failed to load dashboard data:', data.message);
                }
            })
            .catch(error => {
                console.error('Error loading dashboard data:', error);
            });
    }

    // Load chart of accounts
    function loadChartOfAccounts() {
        fetch('api/accounting-api.php?action=get_chart_of_accounts')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('chartOfAccounts');
                tbody.innerHTML = '';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(account => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${account.account_code}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${account.account_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 capitalize">${account.account_type}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$${parseFloat(account.balance).toFixed(2)}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">No accounts found</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading chart of accounts:', error);
                document.getElementById('chartOfAccounts').innerHTML = '<tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">Error loading accounts</td></tr>';
            });
    }

    // Load recent transactions
    function loadRecentTransactions() {
        fetch('api/accounting-api.php?action=get_general_ledger')
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('recentTransactions');
                tbody.innerHTML = '';
                
                if (data.success && data.data.length > 0) {
                    // Show only the 5 most recent transactions
                    const recentTransactions = data.data.slice(0, 5);
                    
                    recentTransactions.forEach(transaction => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${transaction.transaction_date}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">${transaction.description}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${transaction.account_code} - ${transaction.account_name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${transaction.debit_amount > 0 ? '$' + parseFloat(transaction.debit_amount).toFixed(2) : ''}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${transaction.credit_amount > 0 ? '$' + parseFloat(transaction.credit_amount).toFixed(2) : ''}</td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">No transactions found</td></tr>';
                }
            })
            .catch(error => {
                console.error('Error loading transactions:', error);
                document.getElementById('recentTransactions').innerHTML = '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">Error loading transactions</td></tr>';
            });
    }

    // Load accounts for the transaction form
    function loadAccountsForForm() {
        fetch('api/accounting-api.php?action=get_chart_of_accounts')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('account_id');
                select.innerHTML = '<option value="">Select an account</option>';
                
                if (data.success && data.data.length > 0) {
                    data.data.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = `${account.account_code} - ${account.account_name}`;
                        select.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading accounts for form:', error);
            });
    }

    // Open add transaction modal
    function openAddTransactionModal() {
        loadAccountsForForm();
        document.getElementById('addTransactionModal').classList.remove('hidden');
        document.getElementById('addTransactionModal').classList.add('flex');
    }

    // Close add transaction modal
    function closeAddTransactionModal() {
        document.getElementById('addTransactionModal').classList.add('hidden');
        document.getElementById('addTransactionModal').classList.remove('flex');
        document.getElementById('addTransactionForm').reset();
        document.getElementById('transaction_date').valueAsDate = new Date();
    }

    // Add journal entry
    function addJournalEntry() {
        const formData = {
            account_id: document.getElementById('account_id').value,
            transaction_date: document.getElementById('transaction_date').value,
            description: document.getElementById('description').value,
            debit_amount: document.getElementById('debit_amount').value || 0,
            credit_amount: document.getElementById('credit_amount').value || 0,
            reference_type: document.getElementById('reference_type').value
        };
        
        fetch('api/accounting-api.php?action=add_journal_entry', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Transaction added successfully!');
                closeAddTransactionModal();
                loadDashboardData();
                loadChartOfAccounts();
                loadRecentTransactions();
            } else {
                alert('Error adding transaction: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error adding transaction:', error);
            alert('Error adding transaction. Please try again.');
        });
    }

    // Generate financial report
    function generateReport(reportType) {
        const startDate = new Date();
        startDate.setDate(1); // First day of current month
        const endDate = new Date();
        
        const startDateStr = startDate.toISOString().split('T')[0];
        const endDateStr = endDate.toISOString().split('T')[0];
        
        const url = `api/accounting-api.php?action=generate_financial_report&report_type=${reportType}&start_date=${startDateStr}&end_date=${endDateStr}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${reportType.replace('_', ' ')} generated successfully! Report ID: ${data.report_id}`);
                    // In a real implementation, you might want to display the report or redirect to a report view page
                } else {
                    alert('Error generating report: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error generating report:', error);
                alert('Error generating report. Please try again.');
            });
    }
    </script>
</body>
</html>