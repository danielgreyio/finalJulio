<?php
// Get current page filename
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="hidden md:flex md:w-64 md:flex-col fixed inset-y-0 z-10">
    <div class="flex flex-col flex-grow border-r border-gray-200 bg-white overflow-y-auto">
        <div class="flex items-center flex-shrink-0 px-4 h-16 bg-white border-b border-gray-200">
            <a href="../index.php" class="text-xl font-bold text-blue-600 flex items-center gap-2">
                <i class="fas fa-cube"></i> VentDepot
            </a>
        </div>
        <div class="flex-grow flex flex-col">
            <nav class="flex-1 px-2 pb-4 space-y-1 mt-4">
                <!-- Dashboard -->
                <a href="dashboard.php" class="<?= $currentPage == 'dashboard.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-home <?= $currentPage == 'dashboard.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Dashboard
                </a>

                <!-- Core Management -->
                <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Core Management
                </div>
                <a href="users.php" class="<?= $currentPage == 'users.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-users <?= $currentPage == 'users.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Users
                </a>
                <a href="merchants.php" class="<?= $currentPage == 'merchants.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-store <?= $currentPage == 'merchants.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Merchants
                </a>

                <!-- E-Commerce -->
                <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Commerce
                </div>
                <a href="orders.php" class="<?= $currentPage == 'orders.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-shopping-cart <?= $currentPage == 'orders.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Orders
                </a>
                <a href="products.php" class="<?= $currentPage == 'products.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-box <?= $currentPage == 'products.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Products
                </a>
                <a href="inventory.php" class="<?= $currentPage == 'inventory.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-boxes <?= $currentPage == 'inventory.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Inventory
                </a>

                 <!-- Shipping & Logistics -->
                 <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Logistics
                </div>
                <a href="global-shipping-admin.php" class="<?= $currentPage == 'global-shipping-admin.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-globe-americas <?= $currentPage == 'global-shipping-admin.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Global Shipping
                </a>
                <a href="suppliers.php" class="<?= $currentPage == 'suppliers.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-truck <?= $currentPage == 'suppliers.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Suppliers
                </a>

                <!-- Finance -->
                <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Finance
                </div>
                <a href="accounting-dashboard.php" class="<?= $currentPage == 'accounting-dashboard.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-calculator <?= $currentPage == 'accounting-dashboard.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Accounting
                </a>
                <a href="financial-reports.php" class="<?= $currentPage == 'financial-reports.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-chart-line <?= $currentPage == 'financial-reports.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Reports
                </a>
                <a href="commission-tracking.php" class="<?= $currentPage == 'commission-tracking.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-percentage <?= $currentPage == 'commission-tracking.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Commissions
                </a>
                
                <!-- Executive Finance -->
                <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Executive
                </div>
                <a href="c-level-dashboard.php" class="<?= $currentPage == 'c-level-dashboard.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-tachometer-alt <?= $currentPage == 'c-level-dashboard.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    C-Level
                </a>
                <a href="cash-flow-forecasting.php" class="<?= $currentPage == 'cash-flow-forecasting.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-money-bill-wave <?= $currentPage == 'cash-flow-forecasting.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Cash Flow
                </a>
                <a href="budget-vs-actual.php" class="<?= $currentPage == 'budget-vs-actual.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-balance-scale <?= $currentPage == 'budget-vs-actual.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Budgeting
                </a>

                <!-- Content & SEO -->
                <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    Content
                </div>
                <a href="cms-dashboard.php" class="<?= $currentPage == 'cms-dashboard.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-desktop <?= $currentPage == 'cms-dashboard.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    CMS
                </a>
                <a href="cms-banners.php" class="<?= $currentPage == 'cms-banners.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-image <?= $currentPage == 'cms-banners.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Banners
                </a>
                <a href="cms-content.php" class="<?= $currentPage == 'cms-content.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-file-alt <?= $currentPage == 'cms-content.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Pages
                </a>
                <a href="seo-management.php" class="<?= $currentPage == 'seo-management.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-search <?= $currentPage == 'seo-management.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    SEO
                </a>

                <!-- System -->
                <div class="pt-4 pb-2 px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">
                    System
                </div>
                <a href="settings.php" class="<?= $currentPage == 'settings.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-cog <?= $currentPage == 'settings.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    Settings
                </a>
                <a href="accounting-system-status.php" class="<?= $currentPage == 'accounting-system-status.php' ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?> group flex items-center px-2 py-2 text-sm font-medium rounded-md">
                    <i class="fas fa-server <?= $currentPage == 'accounting-system-status.php' ? 'text-gray-500' : 'text-gray-400 group-hover:text-gray-500' ?> mr-3 flex-shrink-0 h-6 w-6 text-center pt-1"></i>
                    System Status
                </a>
            </nav>
        </div>
        <div class="flex-shrink-0 flex border-t border-gray-200 p-4">
            <a href="../logout.php" class="flex-shrink-0 w-full group block">
                <div class="flex items-center">
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-700 group-hover:text-gray-900">
                            Log out
                        </p>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>
