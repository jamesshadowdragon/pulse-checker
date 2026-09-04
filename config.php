<?php
declare(strict_types=1);

/*
 * ============================================================
 * PulseCheck - Public API Configuration
 * ============================================================
 */

/*
 * ------------------------------------------------------------
 * PUBLIC CORS
 * ------------------------------------------------------------
 *
 * Any website can access the API.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-PulseCheck-Token');
header('Access-Control-Max-Age: 86400');
header('Vary: Origin');

/*
 * Handle browser preflight requests.
 */

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json; charset=utf-8');


/*
 * ============================================================
 * File-Based Storage
 * ============================================================
 */

const MONITORS_DIR = __DIR__ . '/monitors';


/*
 * Create monitor directory if it doesn't exist.
 */

if (!is_dir(MONITORS_DIR)) {
    @mkdir(MONITORS_DIR, 0755, true);
}


/*
 * ============================================================
 * JSON Response
 * ============================================================
 */

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/*
 * ============================================================
 * Request Body
 * ============================================================
 */

function input(): array
{
    $raw = file_get_contents('php://input');

    if (!$raw) {
        return [];
    }

    $data = json_decode($raw, true);

    return is_array($data) ? $data : [];
}


/*
 * ============================================================
 * Monitor Token
 * ============================================================
 */

function token(): string
{
    return $_SERVER['HTTP_X_PULSECHECK_TOKEN']
        ?? ($_GET['token'] ?? '');
}


/*
 * ============================================================
 * Generate Secure Token
 * ============================================================
 */

function randomToken(): string
{
    return bin2hex(random_bytes(24));
}


/*
 * ============================================================
 * Validate URL
 * ============================================================
 */

function validUrl(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower(
        (string) parse_url($url, PHP_URL_SCHEME)
    );

    return in_array(
        $scheme,
        ['http', 'https'],
        true
    );
}


/*
 * ============================================================
 * Monitor File Path
 * ============================================================
 */

function monitorPath(int $id): string
{
    return MONITORS_DIR . '/monitor_' . $id . '.json';
}


/*
 * ============================================================
 * Read Monitor
 * ============================================================
 */

function readMonitor(int $id): ?array
{
    $path = monitorPath($id);

    if (!is_file($path)) {
        return null;
    }

    $data = json_decode(
        (string) file_get_contents($path),
        true
    );

    return is_array($data) ? $data : null;
}


/*
 * ============================================================
 * Write Monitor
 * ============================================================
 */

function writeMonitor(array $monitor): bool
{
    $id = (int) $monitor['id'];

    $path = monitorPath($id);
    $tmp = $path . '.tmp';

    $json = json_encode(
        $monitor,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );

    if ($json === false) {
        return false;
    }

    if (
        file_put_contents(
            $tmp,
            $json,
            LOCK_EX
        ) === false
    ) {
        return false;
    }

    return rename($tmp, $path);
}


/*
 * ============================================================
 * Next Monitor ID
 * ============================================================
 */

function nextMonitorId(): int
{
    $max = 0;

    $files = glob(
        MONITORS_DIR . '/monitor_*.json'
    ) ?: [];

    foreach ($files as $file) {

        if (
            preg_match(
                '/monitor_(\d+)\.json$/',
                $file,
                $m
            )
        ) {
            $max = max(
                $max,
                (int) $m[1]
            );
        }
    }

    return $max + 1;
}


/*
 * ============================================================
 * Check URL
 * ============================================================
 */

function checkUrl(string $url): array
{
    $start = microtime(true);

    $ch = curl_init($url);

    if ($ch === false) {
        return [
            'success' => false,
            'status_code' => 0,
            'response_time' => 0,
            'error' => 'Unable to initialize cURL'
        ];
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_USERAGENT => 'PulseCheck/1.0',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    curl_exec($ch);

    $error = curl_error($ch);

    $status = (int) curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    $time = (int) round(
        (microtime(true) - $start) * 1000
    );

    curl_close($ch);

    return [
        'success' =>
            !$error &&
            $status >= 200 &&
            $status < 400,

        'status_code' => $status,

        'response_time' => $time,

        'error' => $error ?: null
    ];
}