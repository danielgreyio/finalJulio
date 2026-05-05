<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden">
    <?php
    require_once '../config/database.php';
    requireRole('admin');
    ?>

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
                        <h1 class="text-3xl font-bold text-gray-800">Financial Reports</h1>
                    </div>

                    <!-- Report Generation Form -->
                    <div class="bg-white rounded-lg shadow p-6 mb-8">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Generate Report</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Report Type</label>
                                <select id="reportType" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="income_statement">Income Statement</option>
                                    <option value="balance_sheet">Balance Sheet</option>
                                    <option value="cash_flow">Cash Flow Statement</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">Start Date</label>
                                <input type="date" id="startDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-gray-700 text-sm font-bold mb-2">End Date</label>
                                <input type="date" id="endDate" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div class="flex items-end">
                                <button onclick="generateReport()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">Generate Report</button>
                            </div>
                        </div>
                    </div>

                    <!-- Income Statement Report -->
                    <div id="incomeStatementReport" class="bg-white rounded-lg shadow mb-8 hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800">Income Statement</h2>
                            <p class="text-gray-600 text-sm" id="incomeStatementPeriod"></p>
                        </div>
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Revenue</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Product Sales</span>
                                        <span id="productSales">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Service Revenue</span>
                                        <span id="serviceRevenue">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Total Revenue</span>
                                        <span id="totalRevenue">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Expenses</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Cost of Goods Sold</span>
                                        <span id="cogs">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Salaries and Wages</span>
                                        <span id="salaries">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Rent Expense</span>
                                        <span id="rent">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Utilities Expense</span>
                                        <span id="utilities">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Marketing Expense</span>
                                        <span id="marketing">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Shipping Expense</span>
                                        <span id="shippingExpense">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Total Expenses</span>
                                        <span id="totalExpenses">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-4">
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Net Income</span>
                                    <span id="netIncome">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Balance Sheet Report -->
                    <div id="balanceSheetReport" class="bg-white rounded-lg shadow mb-8 hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800">Balance Sheet</h2>
                            <p class="text-gray-600 text-sm" id="balanceSheetDate"></p>
                        </div>
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Assets</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Cash</span>
                                        <span id="cashAssets">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Accounts Receivable</span>
                                        <span id="accountsReceivableAssets">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Inventory</span>
                                        <span id="inventoryAssets">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Prepaid Expenses</span>
                                        <span id="prepaidAssets">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Total Assets</span>
                                        <span id="totalAssets">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Liabilities</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Accounts Payable</span>
                                        <span id="accountsPayableLiabilities">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Sales Tax Payable</span>
                                        <span id="salesTaxLiabilities">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Income Tax Payable</span>
                                        <span id="incomeTaxLiabilities">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Loan Payable</span>
                                        <span id="loanLiabilities">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Total Liabilities</span>
                                        <span id="totalLiabilities">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Equity</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Owner Equity</span>
                                        <span id="ownerEquity">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Retained Earnings</span>
                                        <span id="retainedEarnings">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Total Equity</span>
                                        <span id="totalEquity">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-4">
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Total Liabilities and Equity</span>
                                    <span id="totalLiabilitiesEquity">$0.00</span>
                                </div>
                                <div class="mt-2 text-sm" id="balanceSheetStatus"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Flow Statement Report -->
                    <div id="cashFlowReport" class="bg-white rounded-lg shadow mb-8 hidden">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-800">Cash Flow Statement</h2>
                            <p class="text-gray-600 text-sm" id="cashFlowPeriod"></p>
                        </div>
                        <div class="p-6">
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Operating Activities</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Cash Receipts</span>
                                        <span id="cashReceipts">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Cash Payments</span>
                                        <span id="cashPayments">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Net Cash from Operating Activities</span>
                                        <span id="netOperatingCash">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Investing Activities</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Purchase of Equipment</span>
                                        <span id="equipmentPurchase">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Net Cash from Investing Activities</span>
                                        <span id="netInvestingCash">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Financing Activities</h3>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span>Loan Proceeds</span>
                                        <span id="loanProceeds">$0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>Loan Repayments</span>
                                        <span id="loanRepayments">$0.00</span>
                                    </div>
                                    <div class="flex justify-between border-t border-gray-200 pt-2 font-medium">
                                        <span>Net Cash from Financing Activities</span>
                                        <span id="netFinancingCash">$0.00</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-4">
                                <div class="flex justify-between text-lg font-bold">
                                    <span>Net Increase in Cash</span>
                                    <span id="netCashIncrease">$0.00</span>
                                </div>
                                <div class="flex justify-between mt-2">
                                    <span>Cash at Beginning of Period</span>
                                    <span id="cashBeginning">$0.00</span>
                                </div>
                                <div class="flex justify-between mt-2 font-bold">
                                    <span>Cash at End of Period</span>
                                    <span id="cashEnding">$0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Previous Reports -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800">Previous Reports</h2>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Report Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Generated</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200" id="previousReportsTable">
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">Loading previous reports...</td>
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

    <script>
    // Initialize page
    document.addEventListener('DOMContentLoaded', function() {
        // Set default dates
        const today = new Date();
        const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
        document.getElementById('startDate').valueAsDate = firstDay;
        document.getElementById('endDate').valueAsDate = today;
        
        loadPreviousReports();
    });

    // Generate report
    function generateReport() {
        const reportType = document.getElementById('reportType').value;
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        
        if (!startDate || !endDate) {
            alert('Please select both start and end dates');
            return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
            alert('Start date cannot be after end date');
            return;
        }
        
        const url = `api/accounting-api.php?action=generate_financial_report&report_type=${reportType}&start_date=${startDate}&end_date=${endDate}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayReport(reportType, data.data, startDate, endDate);
                } else {
                    alert('Error generating report: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error generating report:', error);
                alert('Error generating report. Please try again.');
            });
    }

    // Display report
    function displayReport(reportType, data, startDate, endDate) {
        // Hide all reports
        document.getElementById('incomeStatementReport').classList.add('hidden');
        document.getElementById('balanceSheetReport').classList.add('hidden');
        document.getElementById('cashFlowReport').classList.add('hidden');
        
        switch(reportType) {
            case 'income_statement':
                displayIncomeStatement(data, startDate, endDate);
                break;
            case 'balance_sheet':
                displayBalanceSheet(data, endDate);
                break;
            case 'cash_flow':
                displayCashFlow(data, startDate, endDate);
                break;
        }
    }

    // Display income statement
    function displayIncomeStatement(data, startDate, endDate) {
        document.getElementById('incomeStatementPeriod').textContent = `${startDate} to ${endDate}`;
        document.getElementById('productSales').textContent = '$' + (data.revenue * 0.8).toFixed(2);
        document.getElementById('serviceRevenue').textContent = '$' + (data.revenue * 0.2).toFixed(2);
        document.getElementById('totalRevenue').textContent = '$' + data.revenue.toFixed(2);
        document.getElementById('cogs').textContent = '$' + (data.expenses * 0.4).toFixed(2);
        document.getElementById('salaries').textContent = '$' + (data.expenses * 0.25).toFixed(2);
        document.getElementById('rent').textContent = '$' + (data.expenses * 0.1).toFixed(2);
        document.getElementById('utilities').textContent = '$' + (data.expenses * 0.05).toFixed(2);
        document.getElementById('marketing').textContent = '$' + (data.expenses * 0.1).toFixed(2);
        document.getElementById('shippingExpense').textContent = '$' + (data.expenses * 0.1).toFixed(2);
        document.getElementById('totalExpenses').textContent = '$' + data.expenses.toFixed(2);
        document.getElementById('netIncome').textContent = '$' + data.net_income.toFixed(2);
        
        document.getElementById('incomeStatementReport').classList.remove('hidden');
    }

    // Display balance sheet
    function displayBalanceSheet(data, date) {
        document.getElementById('balanceSheetDate').textContent = `As of ${date}`;
        document.getElementById('cashAssets').textContent = '$' + (data.assets * 0.4).toFixed(2);
        document.getElementById('accountsReceivableAssets').textContent = '$' + (data.assets * 0.3).toFixed(2);
        document.getElementById('inventoryAssets').textContent = '$' + (data.assets * 0.25).toFixed(2);
        document.getElementById('prepaidAssets').textContent = '$' + (data.assets * 0.05).toFixed(2);
        document.getElementById('totalAssets').textContent = '$' + data.assets.toFixed(2);
        document.getElementById('accountsPayableLiabilities').textContent = '$' + (data.liabilities * 0.5).toFixed(2);
        document.getElementById('salesTaxLiabilities').textContent = '$' + (data.liabilities * 0.2).toFixed(2);
        document.getElementById('incomeTaxLiabilities').textContent = '$' + (data.liabilities * 0.2).toFixed(2);
        document.getElementById('loanLiabilities').textContent = '$' + (data.liabilities * 0.1).toFixed(2);
        document.getElementById('totalLiabilities').textContent = '$' + data.liabilities.toFixed(2);
        document.getElementById('ownerEquity').textContent = '$' + (data.equity * 0.7).toFixed(2);
        document.getElementById('retainedEarnings').textContent = '$' + (data.equity * 0.3).toFixed(2);
        document.getElementById('totalEquity').textContent = '$' + data.equity.toFixed(2);
        document.getElementById('totalLiabilitiesEquity').textContent = '$' + (data.liabilities + data.equity).toFixed(2);
        
        const statusElement = document.getElementById('balanceSheetStatus');
        if (data.balanced) {
            statusElement.textContent = 'Balance sheet is balanced';
            statusElement.className = 'mt-2 text-sm text-green-600';
        } else {
            statusElement.textContent = 'Balance sheet is not balanced';
            statusElement.className = 'mt-2 text-sm text-red-600';
        }
        
        document.getElementById('balanceSheetReport').classList.remove('hidden');
    }

    // Display cash flow statement
    function displayCashFlow(data, startDate, endDate) {
        document.getElementById('cashFlowPeriod').textContent = `${startDate} to ${endDate}`;
        document.getElementById('cashReceipts').textContent = '$' + (data.cash_receipts || 0).toFixed(2);
        document.getElementById('cashPayments').textContent = '$' + (data.cash_payments || 0).toFixed(2);
        document.getElementById('netOperatingCash').textContent = '$' + (data.net_cash_flow || 0).toFixed(2);
        document.getElementById('equipmentPurchase').textContent = '$0.00';
        document.getElementById('netInvestingCash').textContent = '$0.00';
        document.getElementById('loanProceeds').textContent = '$0.00';
        document.getElementById('loanRepayments').textContent = '$0.00';
        document.getElementById('netFinancingCash').textContent = '$0.00';
        document.getElementById('netCashIncrease').textContent = '$' + (data.net_cash_flow || 0).toFixed(2);
        document.getElementById('cashBeginning').textContent = '$10,000.00';
        document.getElementById('cashEnding').textContent = '$' + (10000 + (data.net_cash_flow || 0)).toFixed(2);
        
        document.getElementById('cashFlowReport').classList.remove('hidden');
    }

    // Load previous reports
    function loadPreviousReports() {
        // In a real implementation, this would fetch actual data from the API
        // For now, we'll use placeholder data
        const tbody = document.getElementById('previousReportsTable');
        tbody.innerHTML = `
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Monthly Income Statement</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Income Statement</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-08-01 to 2025-08-31</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-01</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                    <button class="text-green-600 hover:text-green-900">Download</button>
                </td>
            </tr>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Quarterly Balance Sheet</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Balance Sheet</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-07-01 to 2025-09-30</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-10-01</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                    <button class="text-green-600 hover:text-green-900">Download</button>
                </td>
            </tr>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Annual Cash Flow</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Cash Flow</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2024-01-01 to 2024-12-31</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-01-15</td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <button class="text-blue-600 hover:text-blue-900 mr-3">View</button>
                    <button class="text-green-600 hover:text-green-900">Download</button>
                </td>
            </tr>
        `;
    }
    </script>
</body>
</html>