<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/config.php';
    $db = $config['db'];

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $db['name'],
        $db['charset']
    );

    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    return $pdo;
}

function ensure_event_review_status_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = db();
    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'review_status'");
    if (!$stmt->fetch()) {
        $pdo->exec(
            "ALTER TABLE events
             ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER base_score"
        );
        $pdo->exec(
            "UPDATE events
             SET review_status = CASE WHEN active = 1 THEN 'approved' ELSE 'rejected' END"
        );
        $pdo->exec("CREATE INDEX idx_events_review_status ON events (review_status)");
    }

    $checked = true;
}
