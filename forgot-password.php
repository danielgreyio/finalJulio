<?php
require_once 'config/database.php';
require_once 'includes/PasswordReset.php';

$message = null;
$error   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!Security::checkRateLimit('password_reset_' . md5($email), 3, 3600)) {
        $error = 'Too many reset requests. Please wait before trying again.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token    = PasswordReset::generateToken();
            PasswordReset::storeToken($pdo, (int) $user['id'], $token);

            $resetUrl = rtrim(env('APP_URL', ''), '/') . '/reset-password.php?token=' . urlencode($token);

            $mailer = new Mailer();
            $mailer->sendPasswordReset($email, $user['name'], $resetUrl);
        }

        // Always show the same message regardless of whether the email exists (prevents enumeration)
        $message = 'If this email is registered, you\'ll receive a password reset link shortly.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white rounded-xl shadow-md p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Reset your password</h1>
            <p class="text-gray-500 text-sm mt-1">Enter your email and we'll send you a reset link.</p>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-green-800 text-sm">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-red-800 text-sm">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email address</label>
                <input type="email" id="email" name="email" required
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="you@example.com">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-sm transition">
                Send reset link
            </button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-6 text-sm text-gray-500">
            <a href="login.php" class="text-blue-600 hover:underline">← Back to login</a>
        </div>
    </div>
</div>
</body>
</html>
