<?php
require_once '../config/database.php';
requireRole('admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage Manager - VentDepot Admin</title>
    <link rel="stylesheet" href="/assets/css/homepage.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">VentDepot Admin</div>
            <ul class="nav-links">
                <li><a href="/admin">Dashboard</a></li>
                <li><a href="/admin/products">Products</a></li>
                <li><a href="/admin/orders">Orders</a></li>
                <li><a href="/admin/homepage">Homepage</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <div id="admin-homepage-panel" class="admin-homepage-panel">
            <h1>Homepage Manager</h1>
            
            <!-- Banner Management -->
            <section class="admin-section">
                <h2>Banner Management</h2>
                <form id="banner-form">
                    <div class="form-group">
                        <label for="banner-title">Title</label>
                        <input type="text" id="banner-title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-image-url">Image URL</label>
                        <input type="text" id="banner-image-url" name="image_url" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-text-overlay">Text Overlay</label>
                        <textarea id="banner-text-overlay" name="text_overlay"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-button-text">Button Text</label>
                        <input type="text" id="banner-button-text" name="button_text">
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-button-link">Button Link</label>
                        <input type="text" id="banner-button-link" name="button_link">
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-sort-order">Sort Order</label>
                        <input type="number" id="banner-sort-order" name="sort_order" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-start-date">Start Date</label>
                        <input type="datetime-local" id="banner-start-date" name="start_date">
                    </div>
                    
                    <div class="form-group">
                        <label for="banner-end-date">End Date</label>
                        <input type="datetime-local" id="banner-end-date" name="end_date">
                    </div>
                    
                    <div class="form-group">
                        <label>
                            <input type="checkbox" name="is_active" checked> Active
                        </label>
                    </div>
                    
                    <button type="submit">Add Banner</button>
                </form>
                
                <h3>Existing Banners</h3>
                <div id="banners-list">
                    <!-- Banners will be loaded here dynamically -->
                </div>
            </section>
            
            <!-- Featured Products Management -->
            <section class="admin-section">
                <h2>Featured Products</h2>
                <p>Select products to feature on the homepage.</p>
                <!-- Implementation needed -->
            </section>
            
            <!-- Promotional Popups Management -->
            <section class="admin-section">
                <h2>Promotional Popups</h2>
                <p>Manage promotional popup windows.</p>
                <!-- Implementation needed -->
            </section>
            
            <!-- CTA Buttons Management -->
            <section class="admin-section">
                <h2>Call-to-Action Buttons</h2>
                <p>Manage CTA buttons on the homepage.</p>
                <!-- Implementation needed -->
            </section>
            
            <!-- Layout Management -->
            <section class="admin-section">
                <h2>Homepage Layout</h2>
                <p>Arrange homepage sections and toggle visibility.</p>
                <!-- Implementation needed -->
            </section>
        </div>
    </main>

    <footer>
        <p>&copy; 2023 VentDepot. All rights reserved.</p>
    </footer>

    <script src="/assets/js/homepage.js"></script>
</body>
</html>