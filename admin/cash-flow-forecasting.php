<?php
/**
 * Cash Flow Forecasting Report
 * Provides detailed cash flow forecasting with predictive analytics
 */

require_once '../config/database.php';

// Require admin login
requireRole('admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Flow Forecasting - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8">
                        <div>
                            <h1 class="text-3xl font-bold text-gray-900">Cash Flow Forecasting</h1>
                            <p class="text-gray-600 mt-2">Predictive analytics for future financial health</p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <button id="refreshData" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center">
                                    <i class="fas fa-calendar mr-2"></i> Period
                                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10" x-cloak>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="30">Next 30 Days</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="60">Next 60 Days</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="90">Next 90 Days</a>
                                </div>
                            </div>
                            <button class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                        </div>
                    </div>

                    <!-- Key Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Current Cash Balance -->
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-blue-500">
                            <h5 class="text-lg font-semibold text-gray-900">Current Cash Balance</h5>
                            <p class="text-3xl font-bold text-blue-600 mt-2" id="currentCashBalance">$1,250,000</p>
                            <p class="text-sm text-gray-500 mt-1">As of today</p>
                        </div>
                        
                        <!-- 90-Day Runway -->
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-green-500">
                            <h5 class="text-lg font-semibold text-gray-900">90-Day Runway</h5>
                            <p class="text-3xl font-bold text-green-600 mt-2" id="runwayDays">87 days</p>
                            <p class="text-sm text-gray-500 mt-1">Based on current burn rate</p>
                        </div>
                        
                        <!-- Burn Rate -->
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-yellow-500">
                            <h5 class="text-lg font-semibold text-gray-900">Burn Rate</h5>
                            <p class="text-3xl font-bold text-yellow-600 mt-2" id="burnRate">$48,000</p>
                            <p class="text-sm text-gray-500 mt-1">Per month</p>
                        </div>
                        
                        <!-- Confidence Level -->
                        <div class="bg-white rounded-lg shadow p-6 border-l-4 border-cyan-500">
                            <h5 class="text-lg font-semibold text-gray-900">Confidence Level</h5>
                            <p class="text-3xl font-bold text-cyan-600 mt-2" id="confidenceLevel">82%</p>
                            <p class="text-sm text-gray-500 mt-1">Predictive model accuracy</p>
                        </div>
                    </div>

                    <!-- Cash Flow Forecast Chart -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h5 class="text-lg font-semibold text-gray-800">90-Day Cash Flow Forecast</h5>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Daily Forecast
                            </span>
                        </div>
                        <div class="p-6">
                            <canvas id="cashFlowForecastChart" height="120"></canvas>
                        </div>
                    </div>

                    <!-- Cash Flow Components -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                        <!-- Inflows -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-lg font-semibold text-gray-800">Expected Cash Inflows</h5>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Source</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Confidence</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Product Sales</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$45,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-20</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">95%</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Subscription Revenue</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$25,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-22</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">98%</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Accounts Receivable</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$18,500</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-25</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">75%</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Investment Income</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$5,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-30</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">60%</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Outflows -->
                        <div class="bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-lg font-semibold text-gray-800">Expected Cash Outflows</h5>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Payroll</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$85,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-25</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">High</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Inventory Purchase</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$42,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-20</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Medium</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Marketing</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$18,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-22</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Medium</span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Office Rent</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">$12,000</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2025-09-30</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">High</span>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Forecasting Model Details -->
                    <div class="bg-white rounded-lg shadow mb-8" x-data="{ showModal: false }">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h5 class="text-lg font-semibold text-gray-800">Forecasting Model Details</h5>
                            <button @click="showModal = true" class="text-blue-600 hover:text-blue-800 text-sm font-medium flex items-center">
                                <i class="fas fa-info-circle mr-1"></i> Model Information
                            </button>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <h6 class="font-medium text-gray-900 mb-2">Model Performance</h6>
                                    <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
                                        <div class="bg-green-600 h-4 rounded-full" style="width: 82%"></div>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-700">82% Accuracy</p>
                                    <p class="text-xs text-gray-500 mt-1">Based on historical data from the past 12 months</p>
                                </div>
                                <div>
                                    <h6 class="font-medium text-gray-900 mb-2">Key Factors</h6>
                                    <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                        <li>Historical sales trends</li>
                                        <li>Seasonal patterns</li>
                                        <li>Marketing campaign impact</li>
                                        <li>Economic indicators</li>
                                    </ul>
                                </div>
                                <div>
                                    <h6 class="font-medium text-gray-900 mb-2">Model Updates</h6>
                                    <p class="text-sm text-gray-600">Last updated: 2025-09-14</p>
                                    <p class="text-sm text-gray-600">Next scheduled update: 2025-09-21</p>
                                    <button class="mt-2 text-sm text-gray-500 hover:text-gray-900 border border-gray-300 rounded px-2 py-1">Force Model Update</button>
                                </div>
                            </div>
                        </div>

                        <!-- Modal -->
                        <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
                            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="showModal = false"></div>

                                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                                <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                    Forecasting Model Information
                                                </h3>
                                                <div class="mt-4 text-sm text-gray-600 space-y-4">
                                                    <div>
                                                        <h6 class="font-semibold text-gray-800">Model Overview</h6>
                                                        <p>Our cash flow forecasting model uses a combination of time series analysis and machine learning algorithms to predict future cash flows with high accuracy.</p>
                                                    </div>
                                                    
                                                    <div>
                                                        <h6 class="font-semibold text-gray-800">Methodology</h6>
                                                        <ul class="list-disc list-inside">
                                                            <li><strong>Time Series Analysis:</strong> ARIMA models for trend identification</li>
                                                            <li><strong>Machine Learning:</strong> Random Forest algorithms for pattern recognition</li>
                                                            <li><strong>External Factors:</strong> Economic indicators, market trends, and seasonal adjustments</li>
                                                            <li><strong>Validation:</strong> Continuous backtesting against actual results</li>
                                                        </ul>
                                                    </div>
                                                    
                                                    <div>
                                                        <h6 class="font-semibold text-gray-800">Accuracy Metrics</h6>
                                                        <div class="grid grid-cols-2 gap-2">
                                                            <div>
                                                                <p><strong>MAPE:</strong> 12%</p>
                                                                <p><strong>RMSE:</strong> $8,500</p>
                                                            </div>
                                                            <div>
                                                                <p><strong>R-Squared:</strong> 0.85</p>
                                                                <p><strong>Confidence:</strong> ±15%</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div>
                                                        <h6 class="font-semibold text-gray-800">Model Limitations</h6>
                                                        <p>The model may not account for:</p>
                                                        <ul class="list-disc list-inside">
                                                            <li>Unexpected market disruptions</li>
                                                            <li>Sudden changes in customer behavior</li>
                                                            <li>Major economic events not captured in historical data</li>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm" @click="showModal = false">
                                            Close
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts
        initCashFlowForecastChart();
        
        // Refresh data
        document.getElementById('refreshData').addEventListener('click', function() {
            location.reload();
        });
        
        // Period selection
        document.querySelectorAll('[data-period]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const period = this.getAttribute('data-period');
                // In a real implementation, this would fetch data for the selected period
                alert(`Period changed to next ${period} days`);
            });
        });
    });

    function initCashFlowForecastChart() {
        const ctx = document.getElementById('cashFlowForecastChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const dates = [];
        const inflows = [];
        const outflows = [];
        const netCash = [];
        const balance = [];
        
        // Generate sample data for the next 90 days
        const today = new Date();
        let currentBalance = 1250000; // Starting balance
        
        for (let i = 0; i < 13; i++) { // 13 weeks
            const date = new Date(today);
            date.setDate(today.getDate() + (i * 7));
            dates.push(date.toISOString().split('T')[0]);
            
            const weekInflow = 100000 + Math.random() * 50000;
            const weekOutflow = 80000 + Math.random() * 30000;
            
            inflows.push(weekInflow);
            outflows.push(weekOutflow);
            netCash.push(weekInflow - weekOutflow);
            
            currentBalance += (weekInflow - weekOutflow);
            balance.push(currentBalance);
        }
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Cash Inflows',
                        data: inflows,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Cash Outflows',
                        data: outflows,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Net Cash Flow',
                        data: netCash,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        tension: 0.1,
                        fill: false
                    },
                    {
                        label: 'Cash Balance',
                        data: balance,
                        borderColor: 'rgb(153, 102, 255)',
                        backgroundColor: 'rgba(153, 102, 255, 0.1)',
                        tension: 0.1,
                        fill: false,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toLocaleString();
                            }
                        },
                        grid: {
                            drawOnChartArea: false,
                        }
                    }
                }
            }
        });
    }
    </script>
</body>
</html>