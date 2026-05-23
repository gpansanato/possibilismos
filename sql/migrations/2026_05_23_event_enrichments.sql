ALTER TABLE events
    ADD COLUMN canonical_id VARCHAR(120) NULL AFTER source_url,
    ADD COLUMN canonical_source VARCHAR(80) NULL AFTER canonical_id,
    ADD COLUMN canonical_title VARCHAR(255) NULL AFTER canonical_source,
    ADD COLUMN image_url VARCHAR(500) NULL AFTER canonical_title,
    ADD COLUMN enriched_at DATETIME NULL AFTER image_url;

CREATE INDEX idx_events_canonical_id ON events (canonical_id);

CREATE TABLE IF NOT EXISTS event_enrichments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
