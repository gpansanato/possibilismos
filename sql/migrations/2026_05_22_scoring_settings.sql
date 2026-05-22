CREATE TABLE IF NOT EXISTS scoring_settings (
    setting_key VARCHAR(80) NOT NULL PRIMARY KEY,
    setting_value DECIMAL(10,4) NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO scoring_settings (setting_key, setting_value, updated_at) VALUES
    ('historical_weight', 0.45, NOW()),
    ('news_topic_points', 6.0, NOW()),
    ('news_keyword_points', 3.0, NOW()),
    ('news_max', 32.0, NOW()),
    ('anniversary_major', 18.0, NOW()),
    ('anniversary_medium', 14.0, NOW()),
    ('anniversary_minor', 8.0, NOW()),
    ('anniversary_named', 10.0, NOW()),
    ('category_points', 4.0, NOW()),
    ('category_max', 12.0, NOW()),
    ('diversity_penalty', 4.0, NOW()),
    ('diversity_max', 8.0, NOW());
