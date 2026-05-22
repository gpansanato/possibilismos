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
    ['Tabela daily_rankings', table_exists('daily_rankings')],
    ['Tabela scoring_settings', table_exists('scoring_settings')],
    ['events.review_status', column_exists('events', 'review_status')],
    ['events.active', column_exists('events', 'active')],
    ['events.base_score', column_exists('events', 'base_score')],
    ['daily_rankings.context_summary', column_exists('daily_rankings', 'context_summary')],
    ['collected_contexts.normalized_title', column_exists('collected_contexts', 'normalized_title')],
    ['daily_rankings uniq_daily_rankings_event', index_exists('daily_rankings', 'uniq_daily_rankings_event')],
    ['collected_contexts uniq_collected_context', index_exists('collected_contexts', 'uniq_collected_context')],
    ['events idx_events_review_status', index_exists('events', 'idx_events_review_status')],
];

$settings = table_exists('scoring_settings') ? scoring_settings() : [];
$missingSettings = [];
foreach (scoring_setting_definitions() as $key => $definition) {
    if (!array_key_exists($key, $settings)) {
        $missingSettings[] = $key;
    }
}

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
        <h1>Parametros de score</h1>
        <?php if (!$missingSettings): ?>
            <p>Todos os parametros configuraveis do score estao presentes.</p>
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
        <h1>SQL de referencia</h1>
        <p>Se algum item estiver faltando, aplique as migracoes nesta ordem pelo painel MySQL:</p>
        <pre><code>sql/migrations/2026_05_22_event_review_status.sql
sql/migrations/2026_05_22_scoring_settings.sql
sql/migrations/2026_05_22_collected_contexts.sql</code></pre>
    </section>
<?php render_page_end(); ?>
