CREATE TABLE IF NOT EXISTS event_enrichment_statuses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    enrichment_group VARCHAR(80) NOT NULL,
    source VARCHAR(120) NOT NULL,
    status ENUM('pending', 'done', 'empty', 'error', 'skipped') NOT NULL DEFAULT 'pending',
    result_count INT UNSIGNED NOT NULL DEFAULT 0,
    message TEXT NULL,
    attempted_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_event_enrichment_status (event_id, enrichment_group, source),
    INDEX idx_event_enrichment_status_event (event_id),
    INDEX idx_event_enrichment_status_group (enrichment_group, status),
    CONSTRAINT fk_event_enrichment_status_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
