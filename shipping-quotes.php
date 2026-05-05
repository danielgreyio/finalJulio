<?php
require_once 'config/database.php';
require_once 'includes/shipping/ShippingService.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

requireCSRF();

$postal = trim($_POST['destination_postal'] ?? '');

if (!ShippingService::isValidMexicoPostal($postal)) {
    http_response_code(422);
    echo json_encode(['error' => 'Código postal inválido. Debe ser 5 dígitos.']);
    exit;
}

$weight = max(0.1, (float) ($_POST['weight'] ?? 1.0));
$length = max(1.0, (float) ($_POST['length'] ?? 20.0));
$width  = max(1.0, (float) ($_POST['width']  ?? 15.0));
$height = max(1.0, (float) ($_POST['height'] ?? 10.0));

$service = new ShippingService();
$quotes  = $service->getQuotes([
    'destination_postal' => $postal,
    'weight'             => $weight,
    'length'             => $length,
    'width'              => $width,
    'height'             => $height,
]);

echo json_encode(['quotes' => $quotes]);
