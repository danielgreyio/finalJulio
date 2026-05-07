<?php
require_once 'config/database.php';
require_once 'includes/PasswordReset.php';

$token   = $_GET['token'] ?? $_POST['token'] ?? '';
$message = null;
$error   = null;
$tokenRow = null;

if ($token !== '') {
    $tokenRow = PasswordReset::validateToken($pdo, $token);
    if (!$tokenRow) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenRow) {
    $newPassword     = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        $hashed = Security::hashPassword($newPassword);
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $tokenRow['user_id']]);
        PasswordReset::consumeToken($pdo, $token);
        $message = 'Your password has been reset. You can now log in with your new password.';
        $tokenRow = null;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="max-w-md w-full mx-auto px-4">
    <div class="bg-white rounded-xl shadow-md p-8">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Choose a new password</h1>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-md text-green-800 text-sm">
                <?= htmlspecialchars($message) ?>
                <div class="mt-3">
                    <a href="login.php" class="text-blue-600 hover:underline font-medium">Go to login</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-red-800 text-sm">
                <?= htmlspecialchars($error) ?>
                <?php if (!$tokenRow): ?>
                <div class="mt-2">
                    <a href="forgot-password.php" class="text-blue-600 hover:underline">Request a new reset link</a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($tokenRow): ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Security::generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                <input type="password" id="password" name="password" required minlength="8"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="At least 8 characters">
            </div>
            <div>
                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Repeat your new password">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md text-sm transition">
                Set new password
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
