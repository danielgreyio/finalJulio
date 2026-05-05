<?php
/**
 * C-Level Executive Dashboard
 * Provides comprehensive financial reporting for C-Suite executives
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
    <title>C-Level Executive Dashboard - VentDepot</title>
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
                            <h1 class="text-3xl font-bold text-gray-900">C-Level Executive Dashboard</h1>
                            <p class="text-gray-600 mt-2">Executive overview of financial health and growth metrics</p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <button id="refreshDashboard" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center">
                                    <i class="fas fa-calendar mr-2"></i> Period
                                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10" x-cloak>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="7">Last 7 Days</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="30">Last 30 Days</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="90">Last 90 Days</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="365">Last Year</a>
                                </div>
                            </div>
                            <button class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                        </div>
                    </div>

                    <!-- Key Financial Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <!-- Cash Runway -->
                        <div class="bg-blue-600 rounded-lg shadow p-6 text-white">
                            <h5 class="text-lg font-semibold opacity-90">Cash Runway</h5>
                            <p class="text-4xl font-bold mt-2" id="cashRunway">90 days</p>
                            <p class="text-sm mt-1 opacity-75">Burn Rate: $50,000/month</p>
                        </div>
                        
                        <!-- Monthly Revenue -->
                        <div class="bg-green-600 rounded-lg shadow p-6 text-white">
                            <h5 class="text-lg font-semibold opacity-90">Monthly Revenue</h5>
                            <p class="text-4xl font-bold mt-2" id="monthlyRevenue">$1.2M</p>
                            <p class="text-sm mt-1 flex items-center">
                                <i class="fas fa-arrow-up mr-1"></i> 12% from last month
                            </p>
                        </div>
                        
                        <!-- Customer CAC -->
                        <div class="bg-cyan-500 rounded-lg shadow p-6 text-white">
                            <h5 class="text-lg font-semibold opacity-90">Customer CAC</h5>
                            <p class="text-4xl font-bold mt-2" id="customerCAC">$85</p>
                            <p class="text-sm mt-1 flex items-center">
                                <i class="fas fa-arrow-down mr-1"></i> 5% from last month
                            </p>
                        </div>
                        
                        <!-- Churn Rate -->
                        <div class="bg-yellow-400 rounded-lg shadow p-6 text-gray-900">
                            <h5 class="text-lg font-semibold opacity-90">Churn Rate</h5>
                            <p class="text-4xl font-bold mt-2" id="churnRate">2.1%</p>
                            <p class="text-sm mt-1 flex items-center text-gray-800">
                                <i class="fas fa-arrow-up mr-1 text-red-600"></i> 0.3% from last month
                            </p>
                        </div>
                    </div>

                    <!-- Cash Flow Forecasting -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h5 class="text-lg font-semibold text-gray-800">90-Day Cash Flow Forecast</h5>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Confidence: 85%
                            </span>
                        </div>
                        <div class="p-6">
                            <canvas id="cashFlowChart" height="100"></canvas>
                        </div>
                    </div>

                    <!-- Financial Performance -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                        <!-- Budget vs Actual -->
                        <div class="lg:col-span-2 bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-lg font-semibold text-gray-800">Budget vs Actual (Last 30 Days)</h5>
                            </div>
                            <div class="p-6">
                                <canvas id="budgetVarianceChart" height="200"></canvas>
                            </div>
                        </div>
                        
                        <!-- Unit Economics -->
                        <div class="lg:col-span-1 bg-white rounded-lg shadow">
                            <div class="px-6 py-4 border-b border-gray-200">
                                <h5 class="text-lg font-semibold text-gray-800">Unit Economics</h5>
                            </div>
                            <div class="p-6 space-y-4">
                                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                                    <span class="text-gray-600">CAC</span>
                                    <span class="text-xl font-bold text-gray-900" id="unitEconomicsCAC">$85</span>
                                </div>
                                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                                    <span class="text-gray-600">LTV</span>
                                    <span class="text-xl font-bold text-gray-900" id="unitEconomicsLTV">$425</span>
                                </div>
                                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                                    <span class="text-gray-600">LTV/CAC</span>
                                    <span class="text-xl font-bold text-green-600" id="unitEconomicsRatio">5.0x</span>
                                </div>
                                <div class="flex justify-between items-center pb-4 border-b border-gray-100">
                                    <span class="text-gray-600">Payback Period</span>
                                    <span class="text-xl font-bold text-gray-900" id="paybackPeriod">2.1 months</span>
                                </div>
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Gross Margin</span>
                                    <span class="text-xl font-bold text-gray-900" id="grossMargin">68%</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Growth Metrics -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h5 class="text-lg font-semibold text-gray-800">Growth Metrics</h5>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div class="text-center p-4 border border-gray-100 rounded-lg">
                                    <h3 class="text-3xl font-bold text-gray-900" id="arrValue">$14.4M</h3>
                                    <p class="text-gray-500 mt-1">ARR</p>
                                    <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 15%
                                    </span>
                                </div>
                                <div class="text-center p-4 border border-gray-100 rounded-lg">
                                    <h3 class="text-3xl font-bold text-gray-900" id="mrrValue">$1.2M</h3>
                                    <p class="text-gray-500 mt-1">MRR</p>
                                    <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 12%
                                    </span>
                                </div>
                                <div class="text-center p-4 border border-gray-100 rounded-lg">
                                    <h3 class="text-3xl font-bold text-gray-900" id="npsValue">68</h3>
                                    <p class="text-gray-500 mt-1">NPS</p>
                                    <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 5
                                    </span>
                                </div>
                                <div class="text-center p-4 border border-gray-100 rounded-lg">
                                    <h3 class="text-3xl font-bold text-gray-900" id="marketShareValue">12%</h3>
                                    <p class="text-gray-500 mt-1">Market Share</p>
                                    <span class="inline-flex items-center mt-2 px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-arrow-up mr-1"></i> 2%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Risk Management -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                            <h5 class="text-lg font-semibold text-gray-800">Financial Risk Indicators</h5>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                Low Risk
                            </span>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div class="text-center">
                                    <h3 class="text-2xl font-bold text-gray-900" id="currentRatio">2.1</h3>
                                    <p class="text-gray-500 mt-1">Current Ratio</p>
                                </div>
                                <div class="text-center">
                                    <h3 class="text-2xl font-bold text-gray-900" id="quickRatio">1.4</h3>
                                    <p class="text-gray-500 mt-1">Quick Ratio</p>
                                </div>
                                <div class="text-center">
                                    <h3 class="text-2xl font-bold text-gray-900" id="debtEquityRatio">0.3</h3>
                                    <p class="text-gray-500 mt-1">Debt/Equity</p>
                                </div>
                                <div class="text-center">
                                    <h3 class="text-2xl font-bold text-gray-900" id="interestCoverage">15.2</h3>
                                    <p class="text-gray-500 mt-1">Interest Coverage</p>
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
        initCashFlowChart();
        initBudgetVarianceChart();
        
        // Refresh dashboard
        document.getElementById('refreshDashboard').addEventListener('click', function() {
            location.reload();
        });
        
        // Period selection
        document.querySelectorAll('[data-period]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const period = this.getAttribute('data-period');
                // In a real implementation, this would fetch data for the selected period
                alert(`Period changed to last ${period} days`);
            });
        });
    });

    function initCashFlowChart() {
        const ctx = document.getElementById('cashFlowChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const dates = [];
        const inflows = [];
        const outflows = [];
        const netCash = [];
        
        // Generate sample data for the next 90 days
        const today = new Date();
        for (let i = 0; i < 13; i++) { // 13 weeks
            const date = new Date(today);
            date.setDate(today.getDate() + (i * 7));
            dates.push(date.toISOString().split('T')[0]);
            inflows.push(100000 + Math.random() * 50000);
            outflows.push(80000 + Math.random() * 30000);
            netCash.push(inflows[i] - outflows[i]);
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
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1
                    },
                    {
                        label: 'Cash Outflows',
                        data: outflows,
                        borderColor: 'rgb(255, 99, 132)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1
                    },
                    {
                        label: 'Net Cash Flow',
                        data: netCash,
                        borderColor: 'rgb(54, 162, 235)',
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        tension: 0.1
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
                    }
                }
            }
        });
    }

    function initBudgetVarianceChart() {
        const ctx = document.getElementById('budgetVarianceChart').getContext('2d');
        
        // Sample data - in a real implementation, this would come from the API
        const categories = ['Revenue', 'Marketing', 'R&D', 'Operations', 'Salaries'];
        const budget = [1200000, 200000, 150000, 180000, 400000];
        const actual = [1250000, 220000, 140000, 190000, 400000]; // Corrected Salaries to match budget roughly for visualization
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: categories,
                datasets: [
                    {
                        label: 'Budget',
                        data: budget,
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgb(54, 162, 235)',
                        borderWidth: 1
                    },
                    {
                        label: 'Actual',
                        data: actual,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgb(75, 192, 192)',
                        borderWidth: 1
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
                    }
                }
            }
        });
    }
    </script>
</body>
</html>