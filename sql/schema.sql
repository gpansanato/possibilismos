CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_month TINYINT UNSIGNED NOT NULL,
    event_day TINYINT UNSIGNED NOT NULL,
    year INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    category VARCHAR(120) NOT NULL,
    region VARCHAR(120) NOT NULL,
    source_url VARCHAR(500) NULL,
    canonical_id VARCHAR(120) NULL,
    canonical_source VARCHAR(80) NULL,
    canonical_title VARCHAR(255) NULL,
    image_url VARCHAR(500) NULL,
    enriched_at DATETIME NULL,
    base_score DECIMAL(5,2) NOT NULL DEFAULT 50.00,
    review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    active TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX idx_events_day (event_month, event_day),
    INDEX idx_events_canonical_id (canonical_id),
    INDEX idx_events_review_status (review_status),
    INDEX idx_events_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_enrichments (
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

CREATE TABLE daily_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL UNIQUE,
    status ENUM('running', 'done', 'failed') NOT NULL,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE current_topics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL,
    title VARCHAR(190) NOT NULL,
    keywords TEXT NOT NULL,
    source VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_current_topics_date (run_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE collected_contexts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL,
    context_type ENUM('news', 'trend') NOT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scoring_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value DECIMAL(10,4) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE daily_rankings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    run_date DATE NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    score DECIMAL(7,2) NOT NULL,
    reasons TEXT NOT NULL,
    context_summary TEXT NOT NULL,
    status ENUM('suggested', 'approved', 'rejected') NOT NULL DEFAULT 'suggested',
    created_at DATETIME NOT NULL,
    INDEX idx_daily_rankings_date (run_date),
    INDEX idx_daily_rankings_status (status),
    UNIQUE KEY uniq_daily_rankings_event (run_date, event_id),
    CONSTRAINT fk_daily_rankings_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
