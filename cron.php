<?php
require_once __DIR__ . '/config.php';

$secret = getenv('PULSECHECK_CRON_SECRET') ?: 'CHANGE_THIS_CRON_SECRET';

if (!hash_equals($secret, $_GET['secret'] ?? '')) {
    jsonResponse(['error' => 'Unauthorized.'], 401);
}

try {
    $files = glob(MONITORS_DIR . '/monitor_*.json') ?: [];
    usort($files, function ($a, $b) {
        $aa = json_decode((string)file_get_contents($a), true) ?: [];
        $bb = json_decode((string)file_get_contents($b), true) ?: [];
        return strtotime((string)($aa['last_check'] ?? '1970-01-01')) <=> strtotime((string)($bb['last_check'] ?? '1970-01-01'));
    });

    $results = [];

    foreach (array_slice($files, 0, 50) as $file) {
        $monitor = json_decode((string)file_get_contents($file), true);
        if (!is_array($monitor)) continue;

        if (!empty($monitor['last_check']) && strtotime($monitor['last_check']) > time() - 120) {
            continue;
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
        $recent = array_filter($monitor['checks'], fn($c) =>
            isset($c['checked_at']) && strtotime($c['checked_at']) >= $cutoff
        );

        $monitor['uptime'] = count($recent)
            ? round(array_sum(array_map(fn($c) => !empty($c['success']) ? 1 : 0, $recent)) / count($recent) * 100, 2)
            : 0;

        $monitor['response_time'] = count($recent)
            ? (int)round(array_sum(array_map(fn($c) => (int)$c['response_time'], $recent)) / count($recent))
            : 0;

        $monitor['status'] = $result['success'] ? 'up' : 'down';
        $monitor['last_check'] = date('c');

        writeMonitor($monitor);

        $results[] = [
            'id' => (int)$monitor['id'],
            'status' => $monitor['status']
        ];
    }

    jsonResponse([
        'success' => true,
        'checked' => count($results),
        'results' => $results
    ]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error.'], 500);
}
