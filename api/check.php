<?php
// ============================================================
// CORS HEADERS - MUST COME FIRST
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-PulseCheck-Token');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'POST required.'], 405);
}

$provided = token();
if ($provided === '') {
    jsonResponse(['error' => 'Monitor token required.'], 401);
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $monitor = $id > 0 ? readMonitor($id) : null;

    if (!$monitor || !hash_equals((string)$monitor['token'], $provided)) {
        jsonResponse(['error' => 'Invalid monitor token.'], 403);
    }

    $result = checkUrl($monitor['url']);

    $monitor['checks'] ??= [];
    array_unshift($monitor['checks'], [
        'status_code' => $result['status_code'],
        'response_time' => $result['response_time'],
        'success' => $result['success'],
        'error_message' => $result['error'],
        'checked_at' => date('c')
    ]);

    $monitor['checks'] = array_slice($monitor['checks'], 0, 500);

    $cutoff = time() - 86400;
    $recent = array_filter($monitor['checks'], function ($check) use ($cutoff) {
        return isset($check['checked_at']) && strtotime($check['checked_at']) >= $cutoff;
    });

    $uptime = count($recent)
        ? round(array_sum(array_map(fn($c) => !empty($c['success']) ? 1 : 0, $recent)) / count($recent) * 100, 2)
        : 0;

    $avgResponse = count($recent)
        ? (int)round(array_sum(array_map(fn($c) => (int)$c['response_time'], $recent)) / count($recent))
        : 0;

    $monitor['status'] = $result['success'] ? 'up' : 'down';
    $monitor['uptime'] = $uptime;
    $monitor['response_time'] = $avgResponse;
    $monitor['last_check'] = date('c');

    if (!writeMonitor($monitor)) {
        jsonResponse(['error' => 'Could not update monitor file.'], 500);
    }

    jsonResponse([
        'success' => true,
        'monitor_id' => (int)$monitor['id'],
        'status' => $monitor['status'],
        'status_code' => $result['status_code'],
        'response_time' => $result['response_time'],
        'uptime_24h' => (float)$uptime,
        'checked_at' => $monitor['last_check']
    ]);
} catch (Throwable $e) {
    error_log('Check API Error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error.'], 500);
}
?>
