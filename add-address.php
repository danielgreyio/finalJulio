<?php
require_once 'config/database.php';

// Require login
requireLogin();

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addressName = trim($_POST['address_name'] ?? '');
    $recipientName = trim($_POST['recipient_name'] ?? '');
    $addressLine1 = trim($_POST['address_line1'] ?? '');
    $addressLine2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'United States');
    $isDefault = isset($_POST['is_default']);
    
    // Validation
    if (empty($addressName) || empty($recipientName) || empty($addressLine1) || empty($city) || empty($postalCode)) {
        $error = 'Please fill in all required fields.';
    } elseif ($country === 'United States' && empty($state)) {
        $error = 'State is required for United States addresses.';
    } else {
        try {
            $pdo->beginTransaction();
            
            // If this is set as default, remove default from other addresses
            if ($isDefault) {
                $stmt = $pdo->prepare("UPDATE shipping_addresses SET is_default = FALSE WHERE user_id = ?");
                $stmt->execute([$userId]);
            }
            
            // Insert new address
            $stmt = $pdo->prepare("
                INSERT INTO shipping_addresses (user_id, address_name, recipient_name, address_line1, address_line2, city, state, postal_code, country, is_default) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$userId, $addressName, $recipientName, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault])) {
                $pdo->commit();
                $success = 'Address added successfully!';
                
                // Redirect based on user role
                if ($userRole === 'merchant') {
                    header('Location: merchant/profile.php?tab=addresses&success=1');
                } else {
                    header('Location: profile.php?tab=addresses&success=1');
                }
                exit;
            } else {
                $pdo->rollBack();
                $error = 'Failed to add address. Please try again.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'An error occurred while adding the address.';
        }
    }
}

// Get user's existing addresses count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM shipping_addresses WHERE user_id = ?");
$countStmt->execute([$userId]);
$addressCount = $countStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Address - VentDepot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php if ($userRole === 'merchant'): ?>
        <!-- Merchant Navigation -->
        <nav class="bg-white shadow-lg">
            <div class="max-w-7xl mx-auto px-4">
                <div class="flex justify-between items-center py-4">
                    <div class="flex items-center space-x-4">
                        <a href="index.php" class="text-2xl font-bold text-blue-600">VentDepot</a>
                        <span class="text-gray-400">|</span>
                        <a href="merchant/dashboard.php" class="text-lg font-semibold text-gray-700 hover:text-blue-600">Merchant Dashboard</a>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <a href="merchant/profile.php" class="text-gray-600 hover:text-blue-600">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    <?php else: ?>
        <?php include 'includes/navigation.php'; ?>
    <?php endif; ?>

    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Add New Address</h1>
            <p class="text-gray-600 mt-2">
                <?= $userRole === 'merchant' ? 'Add a business address for shipping and operations' : 'Add a new shipping address to your account' ?>
            </p>
        </div>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Address Form -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" class="space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="address_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Address Name *
                        </label>
                        <input type="text" name="address_name" id="address_name" required
                               value="<?= htmlspecialchars($_POST['address_name'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="<?= $userRole === 'merchant' ? 'e.g., Main Office, Warehouse' : 'e.g., Home, Work, Mom\'s House' ?>">
                    </div>
                    
                    <div>
                        <label for="recipient_name" class="block text-sm font-medium text-gray-700 mb-2">
                            <?= $userRole === 'merchant' ? 'Business/Contact Name *' : 'Recipient Name *' ?>
                        </label>
                        <input type="text" name="recipient_name" id="recipient_name" required
                               value="<?= htmlspecialchars($_POST['recipient_name'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               placeholder="<?= $userRole === 'merchant' ? 'Business name or contact person' : 'Full name of recipient' ?>">
                    </div>
                </div>
                
                <div>
                    <label for="address_line1" class="block text-sm font-medium text-gray-700 mb-2">
                        Address Line 1 *
                    </label>
                    <input type="text" name="address_line1" id="address_line1" required
                           value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="Street address, P.O. box, company name">
                </div>
                
                <div>
                    <label for="address_line2" class="block text-sm font-medium text-gray-700 mb-2">
                        Address Line 2
                    </label>
                    <input type="text" name="address_line2" id="address_line2"
                           value="<?= htmlspecialchars($_POST['address_line2'] ?? '') ?>"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                           placeholder="Apartment, suite, unit, building, floor, etc.">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                            City *
                        </label>
                        <input type="text" name="city" id="city" required
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700 mb-2">
                            State/Province *
                        </label>
                        <select name="state" id="state" required
                                class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <option value="">Select State</option>
                            <option value="AL" <?= ($_POST['state'] ?? '') === 'AL' ? 'selected' : '' ?>>Alabama</option>
                            <option value="AK" <?= ($_POST['state'] ?? '') === 'AK' ? 'selected' : '' ?>>Alaska</option>
                            <option value="AZ" <?= ($_POST['state'] ?? '') === 'AZ' ? 'selected' : '' ?>>Arizona</option>
                            <option value="AR" <?= ($_POST['state'] ?? '') === 'AR' ? 'selected' : '' ?>>Arkansas</option>
                            <option value="CA" <?= ($_POST['state'] ?? '') === 'CA' ? 'selected' : '' ?>>California</option>
                            <option value="CO" <?= ($_POST['state'] ?? '') === 'CO' ? 'selected' : '' ?>>Colorado</option>
                            <option value="CT" <?= ($_POST['state'] ?? '') === 'CT' ? 'selected' : '' ?>>Connecticut</option>
                            <option value="DE" <?= ($_POST['state'] ?? '') === 'DE' ? 'selected' : '' ?>>Delaware</option>
                            <option value="FL" <?= ($_POST['state'] ?? '') === 'FL' ? 'selected' : '' ?>>Florida</option>
                            <option value="GA" <?= ($_POST['state'] ?? '') === 'GA' ? 'selected' : '' ?>>Georgia</option>
                            <option value="HI" <?= ($_POST['state'] ?? '') === 'HI' ? 'selected' : '' ?>>Hawaii</option>
                            <option value="ID" <?= ($_POST['state'] ?? '') === 'ID' ? 'selected' : '' ?>>Idaho</option>
                            <option value="IL" <?= ($_POST['state'] ?? '') === 'IL' ? 'selected' : '' ?>>Illinois</option>
                            <option value="IN" <?= ($_POST['state'] ?? '') === 'IN' ? 'selected' : '' ?>>Indiana</option>
                            <option value="IA" <?= ($_POST['state'] ?? '') === 'IA' ? 'selected' : '' ?>>Iowa</option>
                            <option value="KS" <?= ($_POST['state'] ?? '') === 'KS' ? 'selected' : '' ?>>Kansas</option>
                            <option value="KY" <?= ($_POST['state'] ?? '') === 'KY' ? 'selected' : '' ?>>Kentucky</option>
                            <option value="LA" <?= ($_POST['state'] ?? '') === 'LA' ? 'selected' : '' ?>>Louisiana</option>
                            <option value="ME" <?= ($_POST['state'] ?? '') === 'ME' ? 'selected' : '' ?>>Maine</option>
                            <option value="MD" <?= ($_POST['state'] ?? '') === 'MD' ? 'selected' : '' ?>>Maryland</option>
                            <option value="MA" <?= ($_POST['state'] ?? '') === 'MA' ? 'selected' : '' ?>>Massachusetts</option>
                            <option value="MI" <?= ($_POST['state'] ?? '') === 'MI' ? 'selected' : '' ?>>Michigan</option>
                            <option value="MN" <?= ($_POST['state'] ?? '') === 'MN' ? 'selected' : '' ?>>Minnesota</option>
                            <option value="MS" <?= ($_POST['state'] ?? '') === 'MS' ? 'selected' : '' ?>>Mississippi</option>
                            <option value="MO" <?= ($_POST['state'] ?? '') === 'MO' ? 'selected' : '' ?>>Missouri</option>
                            <option value="MT" <?= ($_POST['state'] ?? '') === 'MT' ? 'selected' : '' ?>>Montana</option>
                            <option value="NE" <?= ($_POST['state'] ?? '') === 'NE' ? 'selected' : '' ?>>Nebraska</option>
                            <option value="NV" <?= ($_POST['state'] ?? '') === 'NV' ? 'selected' : '' ?>>Nevada</option>
                            <option value="NH" <?= ($_POST['state'] ?? '') === 'NH' ? 'selected' : '' ?>>New Hampshire</option>
                            <option value="NJ" <?= ($_POST['state'] ?? '') === 'NJ' ? 'selected' : '' ?>>New Jersey</option>
                            <option value="NM" <?= ($_POST['state'] ?? '') === 'NM' ? 'selected' : '' ?>>New Mexico</option>
                            <option value="NY" <?= ($_POST['state'] ?? '') === 'NY' ? 'selected' : '' ?>>New York</option>
                            <option value="NC" <?= ($_POST['state'] ?? '') === 'NC' ? 'selected' : '' ?>>North Carolina</option>
                            <option value="ND" <?= ($_POST['state'] ?? '') === 'ND' ? 'selected' : '' ?>>North Dakota</option>
                            <option value="OH" <?= ($_POST['state'] ?? '') === 'OH' ? 'selected' : '' ?>>Ohio</option>
                            <option value="OK" <?= ($_POST['state'] ?? '') === 'OK' ? 'selected' : '' ?>>Oklahoma</option>
                            <option value="OR" <?= ($_POST['state'] ?? '') === 'OR' ? 'selected' : '' ?>>Oregon</option>
                            <option value="PA" <?= ($_POST['state'] ?? '') === 'PA' ? 'selected' : '' ?>>Pennsylvania</option>
                            <option value="RI" <?= ($_POST['state'] ?? '') === 'RI' ? 'selected' : '' ?>>Rhode Island</option>
                            <option value="SC" <?= ($_POST['state'] ?? '') === 'SC' ? 'selected' : '' ?>>South Carolina</option>
                            <option value="SD" <?= ($_POST['state'] ?? '') === 'SD' ? 'selected' : '' ?>>South Dakota</option>
                            <option value="TN" <?= ($_POST['state'] ?? '') === 'TN' ? 'selected' : '' ?>>Tennessee</option>
                            <option value="TX" <?= ($_POST['state'] ?? '') === 'TX' ? 'selected' : '' ?>>Texas</option>
                            <option value="UT" <?= ($_POST['state'] ?? '') === 'UT' ? 'selected' : '' ?>>Utah</option>
                            <option value="VT" <?= ($_POST['state'] ?? '') === 'VT' ? 'selected' : '' ?>>Vermont</option>
                            <option value="VA" <?= ($_POST['state'] ?? '') === 'VA' ? 'selected' : '' ?>>Virginia</option>
                            <option value="WA" <?= ($_POST['state'] ?? '') === 'WA' ? 'selected' : '' ?>>Washington</option>
                            <option value="WV" <?= ($_POST['state'] ?? '') === 'WV' ? 'selected' : '' ?>>West Virginia</option>
                            <option value="WI" <?= ($_POST['state'] ?? '') === 'WI' ? 'selected' : '' ?>>Wisconsin</option>
                            <option value="WY" <?= ($_POST['state'] ?? '') === 'WY' ? 'selected' : '' ?>>Wyoming</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                            ZIP/Postal Code *
                        </label>
                        <input type="text" name="postal_code" id="postal_code" required
                               value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500"
                               pattern="[0-9]{5}(-[0-9]{4})?"
                               placeholder="12345 or 12345-6789">
                    </div>
                </div>
                
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                        Country *
                    </label>
                    <select name="country" id="country" required
                            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <option value="United States" <?= ($_POST['country'] ?? 'United States') === 'United States' ? 'selected' : '' ?>>United States</option>
                        <option value="Canada" <?= ($_POST['country'] ?? '') === 'Canada' ? 'selected' : '' ?>>Canada</option>
                        <option value="Mexico" <?= ($_POST['country'] ?? '') === 'Mexico' ? 'selected' : '' ?>>Mexico</option>
                    </select>
                </div>
                
                <!-- Default Address Option -->
                <?php if ($addressCount === 0): ?>
                    <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                        <div class="flex items-center">
                            <input type="checkbox" name="is_default" id="is_default" checked disabled
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_default" class="ml-2 text-sm text-blue-800">
                                Set as default address (This will be your first address)
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="is_default" value="1">
                <?php else: ?>
                    <div class="flex items-center">
                        <input type="checkbox" name="is_default" id="is_default" 
                               <?= isset($_POST['is_default']) ? 'checked' : '' ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_default" class="ml-2 text-sm text-gray-700">
                            Set as default address
                        </label>
                    </div>
                <?php endif; ?>
                
                <!-- Form Actions -->
                <div class="flex justify-between items-center pt-6 border-t border-gray-200">
                    <a href="<?= $userRole === 'merchant' ? 'merchant/profile.php' : 'profile.php' ?>" 
                       class="text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>Cancel
                    </a>
                    
                    <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        <i class="fas fa-plus mr-2"></i>Add Address
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-format postal code
        document.getElementById('postal_code').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 9);
            }
            e.target.value = value;
        });
        
        // Country change handler
        const countrySelect = document.getElementById('country');
        const stateSelect = document.getElementById('state');
        const stateLabel = document.querySelector('label[for="state"]');
        
        function updateStateRequirement() {
            if (countrySelect.value === 'United States') {
                stateSelect.required = true;
                stateLabel.innerHTML = 'State/Province *';
                stateSelect.parentElement.style.display = 'block';
            } else {
                stateSelect.required = false;
                stateLabel.innerHTML = 'State/Province (Optional)';
                // Optional: You could hide it entirely for some countries or leave it visible but optional
                // stateSelect.parentElement.style.display = 'none'; 
            }
        }

        countrySelect.addEventListener('change', updateStateRequirement);
        
        // Run on load
        updateStateRequirement();

        // Address validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const postalCode = document.getElementById('postal_code').value;
            const country = document.getElementById('country').value;
            
            // Only validate ZIP format for US
            if (country === 'United States') {
                const postalPattern = /^\d{5}(-\d{4})?$/;
                if (!postalPattern.test(postalCode)) {
                    e.preventDefault();
                    alert('Please enter a valid US ZIP code (12345 or 12345-6789)');
                    return false;
                }
            }
        });
    </script>
</body>
</html>
