CREATE TABLE IF NOT EXISTS collected_contexts (
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
