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
        'trend_topic_points' => 8.0,
        'trend_keyword_points' => 4.0,
        'trend_max' => 28.0,
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

function ensure_collected_contexts_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    db()->exec(
        'CREATE TABLE IF NOT EXISTS collected_contexts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_date DATE NOT NULL,
            context_type ENUM("news", "trend") NOT NULL,
            source VARCHAR(120) NOT NULL,
            title VARCHAR(255) NOT NULL,
            normalized_title VARCHAR(255) NOT NULL,
            keywords TEXT NOT NULL,
            raw_text TEXT NULL,
            source_url VARCHAR(500) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_collected_context (run_date, context_type, source, normalized_title),
            INDEX idx_collected_contexts_date_type (run_date, context_type),
            INDEX idx_collected_contexts_source (source)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $checked = true;
}

function ensure_event_enrichments_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = db();

    $stmt = $pdo->query("SHOW COLUMNS FROM events LIKE 'canonical_id'");
    if (!$stmt->fetch()) {
        $pdo->exec(
            'ALTER TABLE events
             ADD COLUMN canonical_id VARCHAR(120) NULL AFTER source_url,
             ADD COLUMN canonical_source VARCHAR(80) NULL AFTER canonical_id,
             ADD COLUMN canonical_title VARCHAR(255) NULL AFTER canonical_source,
             ADD COLUMN image_url VARCHAR(500) NULL AFTER canonical_title,
             ADD COLUMN enriched_at DATETIME NULL AFTER image_url'
        );
        $pdo->exec('CREATE INDEX idx_events_canonical_id ON events (canonical_id)');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS event_enrichments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            source VARCHAR(120) NOT NULL,
            role VARCHAR(80) NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT NULL,
            source_url VARCHAR(500) NULL,
            image_url VARCHAR(500) NULL,
            license_label VARCHAR(190) NULL,
            external_id VARCHAR(190) NULL,
            metadata_json TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_event_enrichment (event_id, source, role, external_id),
            INDEX idx_event_enrichments_event (event_id),
            INDEX idx_event_enrichments_source (source),
            CONSTRAINT fk_event_enrichments_event
                FOREIGN KEY (event_id) REFERENCES events(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $checked = true;
}

function db_column_exists(string $table, string $column): bool
{
    $stmt = db()->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);

    return (bool) $stmt->fetch();
}

function db_index_exists(string $table, string $index): bool
{
    $stmt = db()->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?');
    $stmt->execute([$index]);

    return (bool) $stmt->fetch();
}

function db_add_column_if_missing(string $table, string $column, string $definition): void
{
    if (!db_column_exists($table, $column)) {
        db()->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $definition);
    }
}

function db_add_index_if_missing(string $table, string $index, string $definition): void
{
    if (!db_index_exists($table, $index)) {
        db()->exec('ALTER TABLE ' . $table . ' ADD ' . $definition);
    }
}

function ensure_event_import_pipeline_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $pdo = db();

    db_add_column_if_missing('events', 'event_key', 'event_key VARCHAR(190) NULL AFTER id');
    db_add_column_if_missing('events', 'normalized_title', 'normalized_title VARCHAR(255) NULL AFTER title');
    db_add_column_if_missing('events', 'confidence_score', 'confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER base_score');
    db_add_column_if_missing('events', 'first_seen_at', 'first_seen_at DATETIME NULL AFTER created_at');
    db_add_column_if_missing('events', 'last_seen_at', 'last_seen_at DATETIME NULL AFTER first_seen_at');
    db_add_column_if_missing('events', 'updated_at', 'updated_at DATETIME NULL AFTER last_seen_at');
    db_add_index_if_missing('events', 'idx_events_event_key', 'INDEX idx_events_event_key (event_key)');
    db_add_index_if_missing('events', 'idx_events_normalized_identity', 'INDEX idx_events_normalized_identity (event_month, event_day, year, normalized_title)');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS event_imports (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            run_date DATE NOT NULL,
            source VARCHAR(120) NOT NULL,
            source_type VARCHAR(80) NOT NULL,
            source_event_id VARCHAR(190) NOT NULL,
            source_url VARCHAR(500) NULL,
            event_month TINYINT UNSIGNED NOT NULL,
            event_day TINYINT UNSIGNED NOT NULL,
            event_year INT NOT NULL,
            raw_title VARCHAR(255) NOT NULL,
            raw_description TEXT NULL,
            raw_category VARCHAR(120) NULL,
            raw_location VARCHAR(190) NULL,
            raw_language VARCHAR(20) NULL,
            raw_payload_json MEDIUMTEXT NULL,
            normalized_key VARCHAR(190) NOT NULL,
            canonical_event_id INT UNSIGNED NULL,
            status ENUM("collected", "normalized", "linked", "ignored", "error") NOT NULL DEFAULT "collected",
            error_message TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_event_import_source_id (source, source_event_id),
            INDEX idx_event_import_source_key (source, normalized_key),
            INDEX idx_event_imports_run_date (run_date),
            INDEX idx_event_imports_canonical_event (canonical_event_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS event_sources (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_id INT UNSIGNED NOT NULL,
            source VARCHAR(120) NOT NULL,
            source_event_id VARCHAR(190) NOT NULL,
            source_url VARCHAR(500) NULL,
            source_title VARCHAR(255) NULL,
            source_description TEXT NULL,
            source_language VARCHAR(20) NULL,
            confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_event_source (event_id, source, source_event_id),
            INDEX idx_event_sources_event (event_id),
            INDEX idx_event_sources_source_id (source, source_event_id),
            CONSTRAINT fk_event_sources_event
                FOREIGN KEY (event_id) REFERENCES events(id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $checked = true;
}

function ensure_event_structured_context_schema(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    db_add_column_if_missing('events', 'wikidata_entities_json', 'wikidata_entities_json TEXT NULL AFTER canonical_title');
    db_add_column_if_missing('events', 'wikidata_location_json', 'wikidata_location_json TEXT NULL AFTER wikidata_entities_json');
    db_add_column_if_missing('events', 'wikidata_relations_json', 'wikidata_relations_json TEXT NULL AFTER wikidata_location_json');

    $checked = true;
}
