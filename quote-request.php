<?php
// Quote Request Form
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_type = $_POST['product_type'] ?? '';
    $specifications = $_POST['specifications'] ?? '';
    $quantity = $_POST['quantity'] ?? 1;
    $timeline = $_POST['timeline'] ?? '';

    // Handle file upload
    $specifications_file = null;
    $fileError = null;
    if (isset($_FILES['specifications_file']) && $_FILES['specifications_file']['error'] === UPLOAD_ERR_OK) {
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'dwg', 'dxf', 'png', 'jpg', 'jpeg'];
        $allowedMimeTypes  = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'image/png',
            'image/jpeg',
            // DWG/DXF are CAD formats with no universal MIME; validate by extension only
        ];

        $ext      = strtolower(pathinfo($_FILES['specifications_file']['name'], PATHINFO_EXTENSION));
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($_FILES['specifications_file']['tmp_name']);

        $isDwgDxf    = in_array($ext, ['dwg', 'dxf'], true);
        $mimeAllowed = in_array($mimeType, $allowedMimeTypes, true);
        $extAllowed  = in_array($ext, $allowedExtensions, true);

        if (!$extAllowed || (!$mimeAllowed && !$isDwgDxf)) {
            $fileError = 'File type not allowed. Accepted: PDF, DOC, XLS, DWG, DXF, images.';
        } else {
            $upload_dir = 'uploads/specifications/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            $file_name   = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $target_file = $upload_dir . $file_name;
            if (move_uploaded_file($_FILES['specifications_file']['tmp_name'], $target_file)) {
                $specifications_file = $target_file;
            }
        }
    }
    
    try {
        // Insert quote request
        $stmt = $pdo->prepare("INSERT INTO quotes (user_id, product_type, specifications, specifications_file, quantity, preferred_timeline) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $product_type, $specifications, $specifications_file, $quantity, $timeline]);
        $quote_id = $pdo->lastInsertId();
        
        // Create notification for sales team
        $stmt = $pdo->prepare("INSERT INTO quote_notifications (quote_id, user_id, notification_type, message) SELECT ?, id, 'quote_submitted', CONCAT('New quote request submitted for ', ?) FROM users WHERE role = 'admin' LIMIT 1");
        $stmt->execute([$quote_id, $product_type]);
        
        $success_message = "Your quote request has been submitted successfully! Our sales team will review it shortly.";
    } catch (PDOException $e) {
        $error_message = "Error submitting quote request: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Engineering Quote - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'includes/header.php'; ?>
    
    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Request Engineering Quote</h1>
            <p class="text-gray-600 mb-8">Submit your product requirements and our engineering team will provide a detailed quote.</p>
            
            <?php if (isset($success_message)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <div>
                    <label for="product_type" class="block text-sm font-medium text-gray-700 mb-1">Product Type *</label>
                    <input type="text" id="product_type" name="product_type" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="mt-1 text-sm text-gray-500">What type of product are you looking to engineer?</p>
                </div>
                
                <div>
                    <label for="specifications" class="block text-sm font-medium text-gray-700 mb-1">Specifications</label>
                    <textarea id="specifications" name="specifications" rows="5"
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Describe your product specifications, materials, dimensions, etc."></textarea>
                    <p class="mt-1 text-sm text-gray-500">Detailed technical specifications for your product</p>
                </div>
                
                <div>
                    <label for="specifications_file" class="block text-sm font-medium text-gray-700 mb-1">Specifications File</label>
                    <input type="file" id="specifications_file" name="specifications_file"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                           accept=".pdf,.doc,.docx,.txt,.jpg,.png,.dwg,.dxf">
                    <p class="mt-1 text-sm text-gray-500">Upload technical drawings, CAD files, or other specification documents</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="timeline" class="block text-sm font-medium text-gray-700 mb-1">Preferred Timeline</label>
                        <select id="timeline" name="timeline"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select timeline</option>
                            <option value="1-2 weeks">1-2 weeks</option>
                            <option value="2-4 weeks">2-4 weeks</option>
                            <option value="1-2 months">1-2 months</option>
                            <option value="2-3 months">2-3 months</option>
                            <option value="Flexible">Flexible</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input id="terms" type="checkbox" required
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="terms" class="ml-2 block text-sm text-gray-700">
                        I agree to the <a href="#" class="text-blue-600 hover:text-blue-500">terms and conditions</a>
                    </label>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <button type="reset" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">
                        Reset
                    </button>
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        Submit Quote Request
                    </button>
                </div>
            </form>
        </div>
        
        <div class="mt-8 bg-white rounded-lg shadow-md p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-4">How It Works</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-file-invoice text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">1. Submit Request</h3>
                    <p class="text-gray-600">Fill out the form with your product specifications and requirements.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-search text-green-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">2. Review & Quote</h3>
                    <p class="text-gray-600">Our sales team reviews your request and provides a detailed quote.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-cogs text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">3. Engineering</h3>
                    <p class="text-gray-600">Our engineers work on your project with progress tracking.</p>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>