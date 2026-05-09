graph TD
    A[Homepage] --> B[Search/Filter Products]
    B --> C[Product Detail Page]
    C --> D[Add to Cart]
    D --> E[Checkout]
    E --> F[Shipping/Banking Selection]
    F --> G[Order Confirmation]
    G --> H[Order Tracking]

Screen	Tech	Features
Homepage	index.php + Tailwind	Hero banner, AI recommendations, "Top Deals" grid
Product Detail	product.php + Alpine.js	Image gallery, color swatches, "Add to Cart" (smooth animation), 5-star ratings
Checkout	checkout.php	Shipping calculator, 3 payment options (mocked), order summary


graph LR
    A[Merchant Login] --> B[Dashboard]
    B --> C[Add New Product]
    C --> D[Upload Images/Details]
    D --> E[Set Price/Inventory]
    E --> F[Product Approval]
    F --> G[Sales Dashboard]

Screen	Tech	Features
Product Creation	merchant/add_product.php	Drag-and-drop image uploader (Dropzone.js), inventory tracking, category selector
Sales Dashboard	merchant/dashboard.php	Real-time sales graph (Chart.js), order management, commission report


graph TD
    A[Admin Login] --> B[Dashboard]
    B --> C[Manage Users]
    C --> D[Verify Merchants]
    B --> E[Monitor Orders]
    E --> F[Refund Requests]
    B --> G[Analytics]
Key Screens (Modern UX)
Screen	Tech	Features
Merchant Approval	admin/merchants.php	List with "Approve/Reject" buttons (AJAX), email notification
Order Management	admin/orders.php	Filter by status (Shipped/Processing), click to view shipping details
Analytics	admin/analytics.php	Interactive charts (Chart.js), revenue by category

Deployment Instructions
=======================

To deploy the finalJulio application to production:

1. Ensure all changes are committed and pushed to the repository:
   git add .
   git commit -m "Production ready updates"
   git push origin master

2. Use the deployment script:
   PowerShell: .\deploy.ps1
   Bash/Linux: ./deploy.sh

3. The deployment script will:
   - Package all files (excluding .git and deployment scripts)
   - Upload to the Linode server at 198.58.124.137
   - Extract files to /var/www/html
   - Set proper permissions
   - Reload Apache service

Manual Deployment (Alternative):
-------------------------------
1. Create archive: tar -czf ventdepot.tar.gz --exclude='.git' .
2. Upload: scp ventdepot.tar.gz root@198.58.124.137:/tmp/
3. SSH into server: ssh root@198.58.124.137
4. Extract: cd /tmp && tar -xzf ventdepot.tar.gz -C /var/www/html
5. Set permissions: chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html
6. Reload Apache: systemctl reload apache2

Windows Batch Deployment (Alternative):
-------------------------------------
1. Double-click deploy.bat or run from command prompt: deploy.bat
2. The script will:
   - Package all files (excluding .git and deployment scripts)
   - Create a ZIP archive
   - Upload to the Linode server at 198.58.124.137
   - Extract files to /var/www/html
   - Set proper permissions
   - Reload Apache service

Note: For Windows deployment, you need:
- PuTTY tools (pscp and plink) installed and in your PATH
- SSH keys configured for authentication to the Linode server
- ZIP/Unzip utilities available on both local and remote systems
