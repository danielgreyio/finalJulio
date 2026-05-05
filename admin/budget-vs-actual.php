<?php
/**
 * Budget vs Actual Reporting
 * Provides detailed budget variance analysis
 */

require_once '../config/database.php';

// Require admin login
requireRole('admin');

$currentPage = 'budget-vs-actual.php'; // For sidebar highlighting if applicable
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget vs Actual Analysis - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-gray-50 h-screen flex overflow-hidden" x-data="{ sidebarOpen: false, recommendationsModalOpen: false }">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Mobile Sidebar Backdrop -->
    <div class="relative z-0 flex-1 flex flex-col overflow-hidden">
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
                            <h1 class="text-3xl font-bold text-gray-900">Budget vs Actual Analysis</h1>
                            <p class="text-gray-600 mt-2">Executive overview of financial health and growth metrics</p>
                        </div>
                        <div class="mt-4 md:mt-0 flex space-x-3">
                            <button id="refreshData" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center shadow-sm text-sm font-medium">
                                <i class="fas fa-sync-alt mr-2"></i> Refresh
                            </button>
                            <div class="relative" x-data="{ open: false }">
                                <button @click="open = !open" class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center shadow-sm text-sm font-medium">
                                    <i class="fas fa-calendar mr-2"></i> Period
                                    <i class="fas fa-chevron-down ml-2 text-xs"></i>
                                </button>
                                <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10" x-cloak>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="month">This Month</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="quarter">This Quarter</a>
                                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100" data-period="year">This Year</a>
                                </div>
                            </div>
                            <button class="bg-white border border-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-50 flex items-center shadow-sm text-sm font-medium">
                                <i class="fas fa-download mr-2"></i> Export
                            </button>
                        </div>
                    </div>

                    <!-- Budget Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                        <div class="bg-white rounded-lg shadow p-6">
                            <h5 class="text-lg font-semibold text-gray-700">Total Budget</h5>
                            <p class="text-3xl font-bold text-blue-600 mt-2" id="totalBudget">$2,450,000</p>
                            <small class="text-gray-500">Annual budget</small>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h5 class="text-lg font-semibold text-gray-700">Actual Spend</h5>
                            <p class="text-3xl font-bold text-green-600 mt-2" id="actualSpend">$1,875,000</p>
                            <small class="text-gray-500">Year to date</small>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h5 class="text-lg font-semibold text-gray-700">Variance</h5>
                            <p class="text-3xl font-bold text-cyan-600 mt-2" id="variance">$575,000</p>
                            <small class="text-gray-500">Under budget</small>
                        </div>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h5 class="text-lg font-semibold text-gray-700">Variance %</h5>
                            <p class="text-3xl font-bold text-yellow-600 mt-2" id="variancePercent">23.5%</p>
                            <small class="text-gray-500">Under budget</small>
                        </div>
                    </div>

                    <!-- Budget Variance Chart -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                            <h5 class="text-lg font-medium text-gray-900">Budget Variance by Category</h5>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                Year to Date
                            </span>
                        </div>
                        <div class="p-6">
                            <div class="relative h-64 sm:h-80">
                                <canvas id="budgetVarianceChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Budget Analysis Table -->
                    <div class="bg-white rounded-lg shadow mb-8 overflow-hidden">
                        <div class="px-6 py-5 border-b border-gray-200">
                            <h5 class="text-lg font-medium text-gray-900">Detailed Budget Analysis</h5>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Department</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Budget</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actual</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Variance %</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Product Development</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">R&D</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$450,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$425,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">-$25,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">-5.6%</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Under Budget
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 font-medium">Details</button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Marketing</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Marketing</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$600,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$625,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right">+$25,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right">+4.2%</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Over Budget
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 font-medium">Details</button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Salaries & Benefits</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">HR</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$950,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$925,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">-$25,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 text-right">-2.6%</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Under Budget
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 font-medium">Details</button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Operations</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Operations</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$300,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$325,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right">+$25,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 text-right">+8.3%</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                Over Budget
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 font-medium">Details</button>
                                        </td>
                                    </tr>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Facilities</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">Admin</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$150,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$150,000</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">$0</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-right">0.0%</td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                On Budget
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <button class="text-blue-600 hover:text-blue-900 font-medium">Details</button>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="bg-blue-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-sm font-bold text-gray-900">Total</th>
                                        <th scope="col" class="px-6 py-3"></th>
                                        <th scope="col" class="px-6 py-3 text-right text-sm font-bold text-gray-900">$2,450,000</th>
                                        <th scope="col" class="px-6 py-3 text-right text-sm font-bold text-gray-900">$2,450,000</th>
                                        <th scope="col" class="px-6 py-3 text-right text-sm font-bold text-gray-900">$0</th>
                                        <th scope="col" class="px-6 py-3 text-right text-sm font-bold text-gray-900">0.0%</th>
                                        <th scope="col" class="px-6 py-3"></th>
                                        <th scope="col" class="px-6 py-3"></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Budget Recommendations -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-5 border-b border-gray-200 flex justify-between items-center">
                            <h5 class="text-lg font-medium text-gray-900">Budget Recommendations</h5>
                            <button @click="recommendationsModalOpen = true" class="text-sm text-blue-600 hover:text-blue-900 font-medium flex items-center">
                                <i class="fas fa-lightbulb mr-2"></i> View All
                            </button>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">Marketing Over Budget</h3>
                                            <div class="mt-2 text-sm text-yellow-700">
                                                <p>Marketing spend is 4.2% over budget. Consider reallocating funds from under-budget categories.</p>
                                            </div>
                                            <div class="mt-4">
                                                <button class="text-sm font-medium text-yellow-800 hover:text-yellow-600">Adjust Budget <span aria-hidden="true">&rarr;</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-green-50 border-l-4 border-green-400 p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-check-circle text-green-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-green-800">R&D Efficiency</h3>
                                            <div class="mt-2 text-sm text-green-700">
                                                <p>Product Development is 5.6% under budget. Consider investing in additional innovation projects.</p>
                                            </div>
                                            <div class="mt-4">
                                                <button class="text-sm font-medium text-green-800 hover:text-green-600">Increase Allocation <span aria-hidden="true">&rarr;</span></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-info-circle text-blue-400"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-blue-800">Operations Monitoring</h3>
                                            <div class="mt-2 text-sm text-blue-700">
                                                <p>Operations spending is trending 8.3% over budget. Review operational efficiency.</p>
                                            </div>
                                            <div class="mt-4">
                                                <button class="text-sm font-medium text-blue-800 hover:text-blue-600">Review Expenses <span aria-hidden="true">&rarr;</span></button>
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

    <!-- Recommendations Modal -->
    <div x-show="recommendationsModalOpen" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true" x-cloak>
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="recommendationsModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" @click="recommendationsModalOpen = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="recommendationsModalOpen" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                            <i class="fas fa-chart-line text-blue-600"></i>
                        </div>
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                Budget Recommendations
                            </h3>
                            <div class="mt-4">
                                <h6 class="font-medium text-gray-900 mb-2">Q3 Budget Optimization Plan</h6>
                                <p class="text-sm text-gray-500 mb-4">Based on current spending patterns and business objectives, we recommend the following budget adjustments:</p>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                    <div>
                                        <h6 class="text-sm font-medium text-gray-900 mb-1">Reallocation Opportunities</h6>
                                        <ul class="list-disc list-inside text-sm text-gray-500 space-y-1">
                                            <li>Transfer $50,000 from Product Development to Marketing</li>
                                            <li>Move $30,000 from Facilities to Operations for efficiency improvements</li>
                                            <li>Reallocate $25,000 from Operations to R&D for new product initiatives</li>
                                        </ul>
                                    </div>
                                    <div>
                                        <h6 class="text-sm font-medium text-gray-900 mb-1">Budget Monitoring Actions</h6>
                                        <ul class="list-disc list-inside text-sm text-gray-500 space-y-1">
                                            <li>Implement weekly marketing spend reviews</li>
                                            <li>Conduct monthly operations efficiency assessments</li>
                                            <li>Establish quarterly budget variance analysis meetings</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <h6 class="font-medium text-gray-900 mb-2">Forecast Impact</h6>
                                <p class="text-sm text-gray-500">These adjustments are projected to improve year-end budget performance by 3.2% while maintaining operational efficiency.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Approve Adjustments
                    </button>
                    <button type="button" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Request Review
                    </button>
                    <button type="button" @click="recommendationsModalOpen = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize charts
        initBudgetVarianceChart();
        
        // Refresh data
        document.getElementById('refreshData').addEventListener('click', function() {
            location.reload();
        });
        
        // Period selection
        document.querySelectorAll('[data-period]').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                const period = this.getAttribute('data-period');
                alert(`Period changed to ${period}`);
            });
        });
    });

    function initBudgetVarianceChart() {
        const ctx = document.getElementById('budgetVarianceChart').getContext('2d');
        
        // Sample data
        const categories = ['Product Development', 'Marketing', 'Salaries & Benefits', 'Operations', 'Facilities'];
        const budget = [450000, 600000, 950000, 300000, 150000];
        const actual = [425000, 625000, 925000, 325000, 150000];
        const variance = budget.map((b, i) => actual[i] - b);
        const variancePercent = budget.map((b, i) => ((actual[i] - b) / b * 100).toFixed(1));
        
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
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                const index = context.dataIndex;
                                return `Variance: $${variance[index].toLocaleString()}\n(${variancePercent[index]}%)`;
                            }
                        }
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