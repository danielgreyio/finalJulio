<?php
require_once 'config/database.php';
require_once 'includes/TwoFactorAuth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$redirect = Security::sanitizeInput($_GET['redirect'] ?? 'index.php', 'string');
$twoFA = new TwoFactorAuth($pdo);
$show2FAForm = false;
$pendingUserId = null;

// Check if we're in 2FA verification mode
if (isset($_SESSION['pending_2fa_user_id'])) {
    $show2FAForm = true;
    $pendingUserId = $_SESSION['pending_2fa_user_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        Security::logSecurityEvent('csrf_token_mismatch', ['page' => 'login']);
        http_response_code(403);
        die('Invalid request. Please go back and try again.');
    }
    
    // Check if this is 2FA verification
    if (isset($_POST['verify_2fa']) && $pendingUserId) {
        $code = $_POST['verification_code'] ?? '';
        $backupCode = $_POST['backup_code'] ?? '';
        $trustDevice = isset($_POST['trust_device']);
        
        $isValid = false;
        
        if ($code) {
            // Verify TOTP code
            $isValid = $twoFA->verifyUserCode($pendingUserId, $code);
        } elseif ($backupCode) {
            // Verify backup code
            $isValid = $twoFA->verifyBackupCode($pendingUserId, $backupCode);
        }
        
        if ($isValid) {
            // Get user data
            $stmt = $pdo->prepare("SELECT id, email, role FROM users WHERE id = ?");
            $stmt->execute([$pendingUserId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Complete login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                
                // Create trusted device if requested
                if ($trustDevice) {
                    $deviceName = 'Web Browser - ' . date('M j, Y');
                    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                    $ipAddress = Security::getClientIP();
                    $twoFA->createTrustedDevice($pendingUserId, $deviceName, $userAgent, $ipAddress);
                }
                
                // Clean up pending session
                unset($_SESSION['pending_2fa_user_id']);
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Log successful login
                Security::logSecurityEvent('user_login_success_2fa', [
                    'user_id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]);
                
                // Validate redirect URL
                $redirect = Security::validateRedirect($redirect);
                header("Location: $redirect");
                exit;
            }
        } else {
            $error = 'Invalid verification code. Please try again.';
            Security::logSecurityEvent('2fa_verification_failed', [
                'user_id' => $pendingUserId,
                'ip' => Security::getClientIP()
            ], 'warning');
        }
    } else {
        // Regular login process
        // Rate limiting - 5 attempts per 15 minutes
        if (!Security::checkRateLimit('login', 5, 900)) {
            $error = 'Too many login attempts. Please wait 15 minutes before trying again.';
            Security::logSecurityEvent('login_rate_limit_exceeded', ['ip' => Security::getClientIP()], 'warning');
        } else {
            // Sanitize input
            $email = Security::sanitizeInput($_POST['email'] ?? '', 'email');
            $password = $_POST['password'] ?? '';
            
            // Validation
            $rules = [
                'email' => ['required' => true, 'type' => 'email'],
                'password' => ['required' => true, 'min_length' => 1]
            ];
            
            $errors = Security::validateInput(['email' => $email, 'password' => $password], $rules);
            
            if (!empty($errors)) {
                $error = implode(' ', $errors);
            } else {
                // Check user credentials
                $stmt = $pdo->prepare("SELECT id, email, password, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && Security::verifyPassword($password, $user['password'])) {
                    // Check if 2FA is enabled
                    if ($twoFA->isEnabled($user['id'])) {
                        // Check if device is trusted
                        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                        $ipAddress = Security::getClientIP();

                        if ($twoFA->isTrustedDevice($user['id'], $userAgent, $ipAddress)) {
                            // Skip 2FA for trusted device
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['user_role'] = $user['role'];

                            session_regenerate_id(true);

                            Security::logSecurityEvent('user_login_success_trusted_device', [
                                'user_id' => $user['id'],
                                'email' => $user['email'],
                                'role' => $user['role']
                            ]);

                            $redirect = Security::validateRedirect($redirect);
                            header("Location: $redirect");
                            exit;
                        } else {
                            // Require 2FA verification
                            $_SESSION['pending_2fa_user_id'] = $user['id'];
                            $show2FAForm = true;
                            $pendingUserId = $user['id'];

                            Security::logSecurityEvent('2fa_required', [
                                'user_id' => $user['id'],
                                'email' => $user['email']
                            ]);
                        }
                    } else {
                        // Login successful without 2FA
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_role'] = $user['role'];

                        session_regenerate_id(true);

                        Security::logSecurityEvent('user_login_success', [
                            'user_id' => $user['id'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ]);

                        $redirect = Security::validateRedirect($redirect);
                        header("Location: $redirect");
                        exit;
                    }
                } else {
                    $error = 'Invalid email or password.';
                    Security::logSecurityEvent('login_failed', [
                        'attempted_email' => $email,
                        'ip' => Security::getClientIP()
                    ], 'warning');
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
    <title>Login - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <a href="index.php" class="text-3xl font-bold text-blue-600">VentDepot</a>
                <?php if ($show2FAForm): ?>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Two-Factor Authentication</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Enter the verification code from your authenticator app
                    </p>
                <?php else: ?>
                    <h2 class="mt-6 text-3xl font-extrabold text-gray-900">Sign in to your account</h2>
                    <p class="mt-2 text-sm text-gray-600">
                        Or 
                        <a href="register.php" class="font-medium text-blue-600 hover:text-blue-500">
                            create a new account
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            
            <?php if ($show2FAForm): ?>
                <!-- 2FA Verification Form -->
                <form class="mt-8 space-y-6" method="POST">
                    <input type="hidden" name="verify_2fa" value="1">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                    
                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <i class="fas fa-shield-alt text-blue-600 mr-2"></i>
                            <span class="text-blue-800 text-sm">
                                Your account is protected with two-factor authentication
                            </span>
                        </div>
                    </div>
                    
                    <div id="totpMethod" class="space-y-4">
                        <div>
                            <label for="verification_code" class="block text-sm font-medium text-gray-700">Verification Code</label>
                            <input id="verification_code" name="verification_code" type="text" 
                                   maxlength="6" pattern="[0-9]{6}" 
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm text-center text-lg tracking-widest"
                                   placeholder="000000">
                            <p class="mt-1 text-xs text-gray-500">Enter the 6-digit code from your authenticator app</p>
                        </div>
                    </div>
                    
                    <div id="backupMethod" class="space-y-4 hidden">
                        <div>
                            <label for="backup_code" class="block text-sm font-medium text-gray-700">Backup Code</label>
                            <input id="backup_code" name="backup_code" type="text" 
                                   maxlength="8" 
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm text-center text-lg tracking-widest"
                                   placeholder="XXXXXXXX">
                            <p class="mt-1 text-xs text-gray-500">Enter one of your backup codes</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="trust_device" name="trust_device" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="trust_device" class="ml-2 block text-sm text-gray-900">
                                Trust this device
                            </label>
                        </div>
                        
                        <div class="text-sm">
                            <button type="button" onclick="toggleBackupMethod()" 
                                    class="font-medium text-blue-600 hover:text-blue-500">
                                <span id="methodToggleText">Use backup code</span>
                            </button>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit"
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-shield-alt text-blue-500 group-hover:text-blue-400"></i>
                            </span>
                            Verify & Sign In
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <a href="login.php" class="text-sm text-gray-600 hover:text-blue-600">
                            ← Back to login
                        </a>
                    </div>
                </form>
            <?php else: ?>
            
                <!-- Regular Login Form -->
                <form class="mt-8 space-y-6" method="POST">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                    
                    <?php if ($error): ?>
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email address</label>
                            <input id="email" name="email" type="email" required
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                                   placeholder="Enter your email">
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                            <input id="password" name="password" type="password" required
                                   class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm"
                                   placeholder="Enter your password">
                        </div>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input id="remember-me" name="remember-me" type="checkbox"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="remember-me" class="ml-2 block text-sm text-gray-900">
                                Remember me
                            </label>
                        </div>
                        
                        <div class="text-sm">
                            <a href="forgot-password.php" class="font-medium text-blue-600 hover:text-blue-500">
                                Forgot your password?
                            </a>
                        </div>
                    </div>
                    
                    <div>
                        <button type="submit"
                                class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                                <i class="fas fa-lock text-blue-500 group-hover:text-blue-400"></i>
                            </span>
                            Sign in
                        </button>
                    </div>
                </form>
            <?php endif; ?>
            
            <!-- Social Login (Mock) -->
            <div class="mt-6">
                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center text-sm">
                        <span class="px-2 bg-gray-50 text-gray-500">Or continue with</span>
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
            
            <?php if (env('APP_ENV') !== 'production'): ?>
            <!-- Demo Accounts -->
            <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
                <h3 class="text-sm font-medium text-yellow-800 mb-2">Demo Accounts</h3>
                <div class="text-xs text-yellow-700 space-y-1">
                    <p><strong>Customer:</strong> customer@demo.com / password123</p>
                    <p><strong>Merchant:</strong> merchant@demo.com / password123</p>
                    <p><strong>Admin:</strong> admin@demo.com / password123</p>
                </div>
            </div>
            <?php endif; ?>
            
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

    <script>
        function toggleBackupMethod() {
            const totpMethod = document.getElementById('totpMethod');
            const backupMethod = document.getElementById('backupMethod');
            const toggleText = document.getElementById('methodToggleText');
            const totpInput = document.getElementById('verification_code');
            const backupInput = document.getElementById('backup_code');
            
            if (totpMethod.classList.contains('hidden')) {
                // Switch to TOTP method
                totpMethod.classList.remove('hidden');
                backupMethod.classList.add('hidden');
                toggleText.textContent = 'Use backup code';
                totpInput.required = true;
                backupInput.required = false;
                backupInput.value = '';
                totpInput.focus();
            } else {
                // Switch to backup code method
                totpMethod.classList.add('hidden');
                backupMethod.classList.remove('hidden');
                toggleText.textContent = 'Use authenticator app';
                backupInput.required = true;
                totpInput.required = false;
                totpInput.value = '';
                backupInput.focus();
            }
        }
        
        // Auto-format verification code input
        document.addEventListener('DOMContentLoaded', function() {
            const verificationCodeInput = document.getElementById('verification_code');
            const backupCodeInput = document.getElementById('backup_code');
            
            if (verificationCodeInput) {
                verificationCodeInput.addEventListener('input', function(e) {
                    // Remove any non-digit characters
                    this.value = this.value.replace(/\D/g, '');
                    
                    // Limit to 6 digits
                    if (this.value.length > 6) {
                        this.value = this.value.slice(0, 6);
                    }
                    
                    // Auto-submit when 6 digits are entered
                    if (this.value.length === 6) {
                        // Small delay to allow user to see the complete code
                        setTimeout(() => {
                            this.form.submit();
                        }, 500);
                    }
                });
                
                // Focus on verification code if 2FA form is shown
                <?php if ($show2FAForm): ?>
                    verificationCodeInput.focus();
                <?php endif; ?>
            }
            
            if (backupCodeInput) {
                backupCodeInput.addEventListener('input', function(e) {
                    // Convert to uppercase and remove spaces/special chars
                    this.value = this.value.replace(/[^A-Z0-9]/g, '').toUpperCase();
                    
                    // Limit to 8 characters
                    if (this.value.length > 8) {
                        this.value = this.value.slice(0, 8);
                    }
                });
            }
        });
    </script>
</body>
</html>
