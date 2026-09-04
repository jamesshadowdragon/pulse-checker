<?php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'GET required.'], 405);
}

$id = (int)($_GET['id'] ?? 0);
$limit = min(max((int)($_GET['limit'] ?? 100), 1), 500);

if ($id < 1) jsonResponse(['error' => 'Monitor ID required.'], 422);

try {
    $monitor = readMonitor($id);
    if (!$monitor) jsonResponse(['error' => 'Monitor not found.'], 404);

    $checks = array_slice($monitor['checks'] ?? [], 0, $limit);
    jsonResponse(['success' => true, 'checks' => $checks]);
} catch (Throwable $e) {
    jsonResponse(['error' => 'Server error.'], 500);
}
