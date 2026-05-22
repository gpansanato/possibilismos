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
    $today = today_key();
    $eventsBefore = events_count_for_day($today['month'], $today['day']);
    $items = run_daily_ranking();
    $eventsAfter = events_count_for_day($today['month'], $today['day']);
    echo json_encode([
        'ok' => true,
        'count' => count($items),
        'approved_events_before' => $eventsBefore,
        'approved_events_after' => $eventsAfter,
        'date' => $today['date'],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}
