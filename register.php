<?php
require_once 'config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Security::logSecurityEvent('csrf_token_mismatch', ['page' => 'register']);
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
    
    // Rate limiting
    if (!Security::checkRateLimit('register', 5, 3600)) { // 5 attempts per hour
        $error = 'Too many registration attempts. Please wait before trying again.';
    } else {
        // Sanitize input
        $sanitizedData = Security::sanitizeArray($_POST, [
            'email' => 'email',
            'password' => 'string',
            'confirm_password' => 'string',
            'role' => 'string'
        ]);
        
        // Validation rules
        $rules = [
            'email' => ['required' => true, 'type' => 'email'],
            'password' => ['required' => true, 'min_length' => 8, 'pattern' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', 'pattern_message' => 'Password must contain at least one lowercase letter, one uppercase letter, one digit, and one special character'],
            'confirm_password' => ['required' => true],
            'role' => ['required' => true, 'in_array' => ['customer', 'merchant']]
        ];
        
        $errors = Security::validateInput($sanitizedData, $rules);
        
        // Check password confirmation
        if (empty($errors) && $sanitizedData['password'] !== $sanitizedData['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match.';
        }
        
        if (!empty($errors)) {
            $error = implode(' ', $errors);
        } else {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$sanitizedData['email']]);
            
            if ($stmt->fetch()) {
                $error = 'Registration could not be completed. Please check your details or try a different email.';
            } else {
                // Create new user
                $hashedPassword = Security::hashPassword($sanitizedData['password']);
                
                $stmt = $pdo->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$sanitizedData['email'], $hashedPassword, $sanitizedData['role']])) {
                    $success = 'Account created successfully! You can now log in.';
                    Security::logSecurityEvent('user_registered', ['email' => $sanitizedData['email'], 'role' => $sanitizedData['role']]);

                    // Auto-login the user
                    $userId = $pdo->lastInsertId();
                    $_SESSION['user_id']    = $userId;
                    $_SESSION['user_email'] = $sanitizedData['email'];
                    $_SESSION['user_role']  = $sanitizedData['role'];

                    // Prevent session fixation after privilege change
                    session_regenerate_id(true);

                    // Redirect based on role
                    if ($sanitizedData['role'] === 'merchant') {
                        header('Location: merchant/dashboard.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                } else {
                    $error = 'Failed to create account. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <a href="index.php" class="text-3xl font-bold text-blue-600">VentDepot</a>
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Create your account</h2>
                <p class="mt-2 text-sm text-gray-600">
                    Already have an account? 
                    <a href="login.php" class="font-medium text-blue-600 hover:text-blue-500">
                        Sign in here
                    </a>
                </p>
            </div>
            
            <!-- Registration Form -->
            <form class="mt-8 space-y-6" method="POST">
                <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-4">
                    <!-- Account Type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative">
                                <input type="radio" name="role" value="customer" 
                                       <?= ($_POST['role'] ?? 'customer') === 'customer' ? 'checked' : '' ?>
                                       class="sr-only peer">
                                <div class="p-4 border-2 border-gray-300 rounded-lg cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-400">
                                    <div class="text-center">
                                        <i class="fas fa-user text-2xl text-gray-600 peer-checked:text-blue-600 mb-2"></i>
                                        <p class="font-medium text-gray-900">Customer</p>
                                        <p class="text-xs text-gray-500">Shop and buy products</p>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="relative">
                                <input type="radio" name="role" value="merchant" 
                                       <?= ($_POST['role'] ?? '') === 'merchant' ? 'checked' : '' ?>
                                       class="sr-only peer">
                                <div class="p-4 border-2 border-gray-300 rounded-lg cursor-pointer peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-gray-400">
                                    <div class="text-center">
                                        <i class="fas fa-store text-2xl text-gray-600 peer-checked:text-blue-600 mb-2"></i>
                                        <p class="font-medium text-gray-900">Merchant</p>
                                        <p class="text-xs text-gray-500">Sell your products</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                        <input id="email" name="email" type="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Enter your email">
                    </div>
                    
                    <!-- Password -->
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <input id="password" name="password" type="password" required
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Create a password (min. 6 characters)">
                    </div>
                    
                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" required
                               class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                               placeholder="Confirm your password">
                    </div>
                </div>
                
                <!-- Terms and Conditions -->
                <div class="flex items-center">
                    <input id="terms" name="terms" type="checkbox" required
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="terms" class="ml-2 block text-sm text-gray-900">
                        I agree to the 
                        <a href="#" class="text-blue-600 hover:text-blue-500">Terms of Service</a> 
                        and 
                        <a href="#" class="text-blue-600 hover:text-blue-500">Privacy Policy</a>
                    </label>
                </div>
                
                <!-- Submit Button -->
                <div>
                    <button type="submit"
                            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <i class="fas fa-user-plus text-blue-500 group-hover:text-blue-400"></i>
                        </span>
                        Create Account
                    </button>
                </div>
            </form>
            
            <!-- Social Registration (Mock) -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-50 text-gray-500">Or register with</span>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <i class="fab fa-google text-red-500"></i>
                        <span class="ml-2">Google</span>
                    </button>
                    
                    <button class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                        <i class="fab fa-facebook text-blue-600"></i>
                        <span class="ml-2">Facebook</span>
                    </button>
                </div>
            </div>
            
            <!-- Merchant Info -->
            <div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-md">
                <h3 class="text-sm font-medium text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Merchant Account Benefits
                </h3>
                <ul class="text-xs text-blue-700 space-y-1">
                    <li>• List and sell your products</li>
                    <li>• Access to merchant dashboard</li>
                    <li>• Real-time sales analytics</li>
                    <li>• Commission-based pricing</li>
                </ul>
            </div>
            
            <!-- Footer Links -->
            <div class="text-center text-sm text-gray-600">
                <a href="index.php" class="hover:text-blue-600">← Back to Home</a>
                <span class="mx-2">|</span>
                <a href="#" class="hover:text-blue-600">Help</a>
                <span class="mx-2">|</span>
                <a href="#" class="hover:text-blue-600">Privacy</a>
            </div>
        </div>
    </div>
</body>
</html>
