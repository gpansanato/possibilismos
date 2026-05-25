<?php

$config = require __DIR__ . '/config.php';
date_default_timezone_set($config['timezone']);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/components.php';
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/events.php';
require_once __DIR__ . '/event_imports.php';
require_once __DIR__ . '/historical_sources.php';
require_once __DIR__ . '/sources.php';
require_once __DIR__ . '/ranking.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name($config['admin']['session_name']);
    session_start();
}

ensure_event_review_status_schema();
ensure_scoring_settings_schema();
ensure_collected_contexts_schema();
ensure_event_enrichments_schema();
ensure_event_import_pipeline_schema();
ensure_event_structured_context_schema();
