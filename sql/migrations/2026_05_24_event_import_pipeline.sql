ALTER TABLE events
    ADD COLUMN event_key VARCHAR(190) NULL AFTER id,
    ADD COLUMN normalized_title VARCHAR(255) NULL AFTER title,
    ADD COLUMN confidence_score DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER base_score,
    ADD COLUMN first_seen_at DATETIME NULL AFTER created_at,
    ADD COLUMN last_seen_at DATETIME NULL AFTER first_seen_at,
    ADD COLUMN updated_at DATETIME NULL AFTER last_seen_at;

CREATE INDEX idx_events_event_key ON events (event_key);
CREATE INDEX idx_events_normalized_identity ON events (event_month, event_day, year, normalized_title);

CREATE TABLE IF NOT EXISTS event_imports (
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
    status ENUM('collected', 'normalized', 'linked', 'ignored', 'error') NOT NULL DEFAULT 'collected',
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_event_import_source_id (source, source_event_id),
    INDEX idx_event_import_source_key (source, normalized_key),
    INDEX idx_event_imports_run_date (run_date),
    INDEX idx_event_imports_canonical_event (canonical_event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_sources (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
