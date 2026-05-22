<?php
require_once __DIR__ . '/../app/bootstrap.php';

$config = require __DIR__ . '/../app/config.php';
$token = $_GET['token'] ?? '';

if (!hash_equals($config['cron']['token'], $token)) {
    http_response_code(403);
    echo 'Token invalido.';
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $items = run_daily_ranking();
    echo json_encode([
        'ok' => true,
        'count' => count($items),
        'date' => today_key()['date'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}
