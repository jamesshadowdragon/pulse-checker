<?php
// ============================================================
// CORS PROXY - Handles all CORS issues
// ============================================================

// Force CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-PulseCheck-Token');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Determine which API endpoint to forward to
$path = $_GET['path'] ?? '';
$target = '';

// Route to the correct API endpoint
switch ($path) {
    case 'monitors':
        $target = '/api/monitors.php';
        break;
    case 'check':
        $target = '/api/check.php';
        break;
    case 'history':
        $target = '/api/history.php';
        break;
    default:
        // If no path specified, default to monitors
        $target = '/api/monitors.php';
        break;
}

// Add query string if present
if ($_SERVER['QUERY_STRING']) {
    // Remove 'path' parameter from query string
    parse_str($_SERVER['QUERY_STRING'], $params);
    unset($params['path']);
    if (!empty($params)) {
        $target .= '?' . http_build_query($params);
    }
}

// Read request body
$body = file_get_contents('php://input');

// Build the full URL
$url = 'http://localhost' . $target;

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Forward the request body for POST/DELETE
if ($body && ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'DELETE')) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

// Forward headers
$headers = [];
foreach (getallheaders() as $name => $value) {
    if (strpos($name, 'X-PulseCheck-Token') !== false) {
        $headers[] = "$name: $value";
    }
}
if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge([
        'Content-Type: application/json'
    ], $headers));
}

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Return the response
if ($error) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Proxy error',
        'message' => $error
    ]);
} else {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo $response;
}
?>
