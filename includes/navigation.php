<!-- Navigation -->
<nav class="bg-white shadow-lg sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            <div class="flex items-center space-x-4">
                <a href="index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
            </div>
            
            <!-- Search Bar -->
            <div class="flex-1 max-w-lg mx-8">
                <form action="search.php" method="GET" class="relative">
                    <input type="text" name="q" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>" 
                           placeholder="Search products..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <button type="submit" class="absolute right-3 top-2.5 text-gray-400 hover:text-gray-600">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                <a href="cart.php" class="relative text-gray-600 hover:text-blue-600">
                    <i class="fas fa-shopping-cart text-xl"></i>
                    <?php if (getCartCount() > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center">
                            <?= getCartCount() ?>
                        </span>
                    <?php endif; ?>
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <div class="relative">
                        <button id="userMenuButton" class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 focus:outline-none">
                            <i class="fas fa-user"></i>
                            <span><?= htmlspecialchars($_SESSION['user_email'] ?? 'User') ?></span>
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>
                        <div id="userMenuDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 hidden">
                            <a href="profile.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="orders.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">My Orders</a>
                            <?php if (getUserRole() === 'merchant'): ?>
                                <a href="merchant/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Merchant Dashboard</a>
                            <?php endif; ?>
                            <?php if (getUserRole() === 'admin'): ?>
                                <a href="admin/dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Admin Dashboard</a>
                                <a href="sales-dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Engineering Sales</a>
                                <a href="engineering-dashboard.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Engineering Tasks</a>
                            <?php endif; ?>
                            <a href="logout.php" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Logout</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="text-gray-600 hover:text-blue-600">Login</a>
                    <a href="register.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const userMenuButton = document.getElementById('userMenuButton');
    const userMenuDropdown = document.getElementById('userMenuDropdown');
    
    if (userMenuButton && userMenuDropdown) {
        userMenuButton.addEventListener('click', function(e) {
            e.stopPropagation();
            userMenuDropdown.classList.toggle('hidden');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userMenuButton.contains(e.target) && !userMenuDropdown.contains(e.target)) {
                userMenuDropdown.classList.add('hidden');
            }
        });
    }
});
</script>
<script>
(function() {
    var t = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? \Security::generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>';
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form[method="post"],form[method="POST"]').forEach(function(f) {
            if (!f.querySelector('[name="csrf_token"]')) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = 'csrf_token'; i.value = t;
                f.prepend(i);
            }
        });
    });
})();
</script>
