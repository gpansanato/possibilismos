CREATE TABLE IF NOT EXISTS event_collector_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL,
    source VARCHAR(120) NOT NULL,
    source_variant VARCHAR(120) NOT NULL,
    status ENUM('pending', 'running', 'done', 'error', 'skipped') NOT NULL DEFAULT 'pending',
    found_count INT UNSIGNED NOT NULL DEFAULT 0,
    imported_count INT UNSIGNED NOT NULL DEFAULT 0,
    error_count INT UNSIGNED NOT NULL DEFAULT 0,
    message TEXT NULL,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_event_collector_status (run_date, source, source_variant),
    INDEX idx_event_collector_status_date (run_date, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
