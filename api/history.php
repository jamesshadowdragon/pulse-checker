<?php
// ============================================================
// CORS HEADERS - MUST COME FIRST
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-PulseCheck-Token');
header('Access-Control-Max-Age: 86400');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // GET - Fetch monitors
    if ($method === 'GET') {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

        if ($id > 0) {
            $monitor = readMonitor($id);
            if (!$monitor) jsonResponse(['error' => 'Monitor not found.'], 404);

            unset($monitor['token'], $monitor['checks']);
            jsonResponse(['success' => true, 'monitor' => $monitor]);
        }

        $monitors = [];
        foreach (glob(MONITORS_DIR . '/monitor_*.json') ?: [] as $file) {
            $monitor = json_decode((string)file_get_contents($file), true);
            if (!is_array($monitor)) continue;

            unset($monitor['token'], $monitor['checks']);
            $monitors[] = $monitor;
        }

        usort($monitors, fn($a, $b) => (int)$b['id'] <=> (int)$a['id']);
        jsonResponse(['success' => true, 'monitors' => $monitors]);
    }

    // POST - Create new monitor
    if ($method === 'POST') {
        $data = input();
        $url = trim((string)($data['url'] ?? ''));

        if (!validUrl($url)) {
            jsonResponse(['error' => 'A valid HTTP/HTTPS URL is required.'], 422);
        }

        $id = nextMonitorId();
        $monitorToken = randomToken();

        $monitor = [
            'id' => $id,
            'url' => $url,
            'token' => $monitorToken,
            'status' => 'pending',
            'uptime' => 0.00,
            'response_time' => 0,
            'last_check' => null,
            'created_at' => date('c'),
            'checks' => []
        ];

        if (!writeMonitor($monitor)) {
            jsonResponse(['error' => 'Could not create monitor file. Check folder permissions.'], 500);
        }

        jsonResponse([
            'success' => true,
            'monitor' => [
                'id' => $id,
                'url' => $url,
                'token' => $monitorToken,
                'status' => 'pending'
            ]
        ], 201);
    }

    // DELETE - Remove monitor
    if ($method === 'DELETE') {
        $data = input();
        $id = (int)($data['id'] ?? $_GET['id'] ?? 0);
        $provided = token();

        if ($id < 1 || $provided === '') {
            jsonResponse(['error' => 'Monitor ID and token are required.'], 422);
        }

        $monitor = readMonitor($id);
        if (!$monitor || !hash_equals((string)$monitor['token'], $provided)) {
            jsonResponse(['error' => 'Monitor not found or invalid token.'], 404);
        }

        if (!unlink(monitorPath($id))) {
            jsonResponse(['error' => 'Could not delete monitor file.'], 500);
        }

        jsonResponse(['success' => true]);
    }

    jsonResponse(['error' => 'Method not allowed.'], 405);
} catch (Throwable $e) {
    error_log('Monitor API Error: ' . $e->getMessage());
    jsonResponse(['error' => 'Server error.'], 500);
}
?>
