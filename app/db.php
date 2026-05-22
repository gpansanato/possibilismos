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

function ensure_scoring_settings_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS scoring_settings (
            setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
            setting_value DECIMAL(10,4) NOT NULL,
            updated_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $defaults = [
        'historical_weight' => 0.45,
        'news_topic_points' => 6.0,
        'news_keyword_points' => 3.0,
        'news_max' => 32.0,
        'anniversary_major' => 18.0,
        'anniversary_medium' => 14.0,
        'anniversary_minor' => 8.0,
        'anniversary_named' => 10.0,
        'category_points' => 4.0,
        'category_max' => 12.0,
        'diversity_penalty' => 4.0,
        'diversity_max' => 8.0,
    ];

    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO scoring_settings (setting_key, setting_value, updated_at)
         VALUES (?, ?, NOW())'
    );

    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    $checked = true;
}
