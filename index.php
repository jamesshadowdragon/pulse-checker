<?php
require_once __DIR__ . '/config.php';

jsonResponse([
    'name' => 'PulseCheck API',
    'version' => '2.0.0',
    'storage' => 'file-based',
    'status' => 'online'
]);
