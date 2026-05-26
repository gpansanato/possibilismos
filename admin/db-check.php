<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

function table_exists(string $table): bool
{
    $stmt = db()->prepare('SHOW TABLES LIKE ?');
    $stmt->execute([$table]);

    return (bool) $stmt->fetch();
}

function column_exists(string $table, string $column): bool
{
    if (!table_exists($table)) {
        return false;
    }

    $stmt = db()->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?');
    $stmt->execute([$column]);

    return (bool) $stmt->fetch();
}

function index_exists(string $table, string $index): bool
{
    if (!table_exists($table)) {
        return false;
    }

    $stmt = db()->prepare('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ?');
    $stmt->execute([$index]);

    return (bool) $stmt->fetch();
}

$checks = [
    ['Tabela users', table_exists('users')],
    ['Tabela events', table_exists('events')],
    ['Tabela daily_runs', table_exists('daily_runs')],
    ['Tabela current_topics', table_exists('current_topics')],
    ['Tabela collected_contexts', table_exists('collected_contexts')],
    ['Tabela event_imports', table_exists('event_imports')],
    ['Tabela event_sources', table_exists('event_sources')],
    ['Tabela event_enrichments', table_exists('event_enrichments')],
    ['Tabela event_enrichment_statuses', table_exists('event_enrichment_statuses')],
    ['Tabela event_collector_statuses', table_exists('event_collector_statuses')],
    ['Tabela daily_rankings', table_exists('daily_rankings')],
    ['Tabela scoring_settings', table_exists('scoring_settings')],
    ['events.review_status', column_exists('events', 'review_status')],
    ['events.active', column_exists('events', 'active')],
    ['events.base_score', column_exists('events', 'base_score')],
    ['events.canonical_id', column_exists('events', 'canonical_id')],
    ['events.event_key', column_exists('events', 'event_key')],
    ['events.normalized_title', column_exists('events', 'normalized_title')],
    ['events.confidence_score', column_exists('events', 'confidence_score')],
    ['events.image_url', column_exists('events', 'image_url')],
    ['daily_rankings.context_summary', column_exists('daily_rankings', 'context_summary')],
    ['collected_contexts.normalized_title', column_exists('collected_contexts', 'normalized_title')],
    ['daily_rankings uniq_daily_rankings_event', index_exists('daily_rankings', 'uniq_daily_rankings_event')],
    ['collected_contexts uniq_collected_context', index_exists('collected_contexts', 'uniq_collected_context')],
    ['event_enrichments uniq_event_enrichment', index_exists('event_enrichments', 'uniq_event_enrichment')],
    ['event_imports uniq_event_import_source_id', index_exists('event_imports', 'uniq_event_import_source_id')],
    ['event_imports idx_event_import_source_key', index_exists('event_imports', 'idx_event_import_source_key')],
    ['event_imports idx_event_imports_run_date', index_exists('event_imports', 'idx_event_imports_run_date')],
    ['event_imports idx_event_imports_canonical_event', index_exists('event_imports', 'idx_event_imports_canonical_event')],
    ['event_imports idx_event_imports_source_variant', index_exists('event_imports', 'idx_event_imports_source_variant')],
    ['event_sources uniq_event_source', index_exists('event_sources', 'uniq_event_source')],
    ['event_sources idx_event_sources_source_id', index_exists('event_sources', 'idx_event_sources_source_id')],
    ['event_sources idx_event_sources_source_variant', index_exists('event_sources', 'idx_event_sources_source_variant')],
    ['event_enrichment_statuses uniq_event_enrichment_status', index_exists('event_enrichment_statuses', 'uniq_event_enrichment_status')],
    ['event_collector_statuses uniq_event_collector_status', index_exists('event_collector_statuses', 'uniq_event_collector_status')],
    ['events idx_events_event_key', index_exists('events', 'idx_events_event_key')],
    ['events idx_events_review_status', index_exists('events', 'idx_events_review_status')],
];

$settings = table_exists('scoring_settings') ? scoring_settings() : [];
$missingSettings = [];
foreach (scoring_setting_definitions() as $key => $definition) {
    if (!array_key_exists($key, $settings)) {
        $missingSettings[] = $key;
    }
}

$today = today_key();
$contextCountToday = table_exists('collected_contexts')
    ? collected_contexts_count_for_date($today['date'])
    : 0;

render_page_start('Diagnostico do banco', 'db-check', 'admin', 'Verifica se o banco esta alinhado com as tabelas, colunas e parametros esperados pela aplicacao.');
?>
    <section class="panel">
        <h1>Checklist estrutural</h1>
        <div class="check-list">
            <?php foreach ($checks as [$label, $ok]): ?>
                <div class="check-item">
                    <span class="status-badge <?= $ok ? 'is-approved' : 'is-rejected' ?>">
                        <?= $ok ? 'OK' : 'Falta' ?>
                    </span>
                    <strong><?= h($label) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel">
        <h1>Parametros de priorizacao</h1>
        <?php if (!$missingSettings): ?>
            <p>Todos os parametros configuraveis da priorizacao estao presentes.</p>
        <?php else: ?>
            <p>Parametros ausentes:</p>
            <ul>
                <?php foreach ($missingSettings as $key): ?>
                    <li><?= h($key) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h1>Base higienizada</h1>
        <p>Itens persistidos para hoje: <?= h((string) $contextCountToday) ?></p>
        <p><a href="/admin/contexts.php">Abrir base higienizada</a></p>
    </section>

    <section class="panel">
        <h1>SQL de referencia</h1>
        <p>Se algum item estiver faltando, aplique as migracoes nesta ordem pelo painel MySQL:</p>
        <pre><code>sql/migrations/2026_05_22_event_review_status.sql
sql/migrations/2026_05_22_scoring_settings.sql
sql/migrations/2026_05_22_collected_contexts.sql
sql/migrations/2026_05_23_event_enrichments.sql
sql/migrations/2026_05_24_event_import_pipeline.sql
sql/migrations/2026_05_25_event_source_variants.sql
sql/migrations/2026_05_26_event_import_indexes.sql
sql/migrations/2026_05_26_event_enrichment_statuses.sql
sql/migrations/2026_05_26_event_collector_statuses.sql</code></pre>
    </section>
<?php render_page_end(); ?>
