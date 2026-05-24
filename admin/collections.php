<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$date = $_GET['date'] ?? $today['date'];
if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today['date'];
}
$dateParts = date_parts_from_run_date($date);

$eventCounts = events_count_by_review_status($dateParts['month'], $dateParts['day']);
$historicalSummary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
$importSummary = event_import_summary_for_date($date);
$newsCount = collected_contexts_count_for_date($date, 'news');
$trendCount = collected_contexts_count_for_date($date, 'trend');
$topicsCount = current_topics_count_for_date($date);
$rankingCount = rankings_count_for_date($date);
$collectionRows = [
    [
        'date' => $date,
        'name' => 'Eventos históricos',
        'status' => $historicalSummary['total'] > 0 ? 'Concluída' : 'Pendente',
        'count' => $historicalSummary['total'],
        'detail' => $eventCounts['pending'] . ' não publicados, ' . $eventCounts['approved'] . ' publicados, ' . $eventCounts['rejected'] . ' reprovados; ' . $importSummary['linked'] . ' imports vinculados',
        'href' => '/admin/events.php?date=' . $date,
    ],
    [
        'date' => $date,
        'name' => 'Normalização de eventos',
        'status' => $importSummary['total'] > 0 ? 'Concluída' : 'Pendente',
        'count' => $importSummary['total'],
        'detail' => $importSummary['linked'] . ' vinculados, ' . $importSummary['ignored'] . ' ignorados, ' . $importSummary['errors'] . ' erros',
        'href' => '/admin/events.php?date=' . $date,
    ],
    [
        'date' => $date,
        'name' => 'Enriquecimento histórico',
        'status' => $historicalSummary['enriched'] > 0 ? 'Em andamento' : 'Pendente',
        'count' => $historicalSummary['enrichment_records'],
        'detail' => $historicalSummary['enriched'] . ' eventos enriquecidos, ' . $historicalSummary['not_enriched'] . ' pendentes',
        'href' => '/admin/events.php?date=' . $date . '&enrichment=not_enriched',
    ],
    [
        'date' => $date,
        'name' => 'Notícias',
        'status' => $newsCount > 0 ? 'Concluída' : 'Pendente',
        'count' => $newsCount,
        'detail' => 'Itens higienizados em collected_contexts',
        'href' => '/admin/contexts.php?date=' . $date . '&type=news',
    ],
    [
        'date' => $date,
        'name' => 'Tendências',
        'status' => $trendCount > 0 ? 'Concluída' : 'Pendente',
        'count' => $trendCount,
        'detail' => 'GDELT, Media Cloud, Wikimedia, Agência Brasil e Hacker News',
        'href' => '/admin/contexts.php?date=' . $date . '&type=trend',
    ],
    [
        'date' => $date,
        'name' => 'Tópicos de priorização',
        'status' => $topicsCount > 0 ? 'Disponível' : 'Pendente',
        'count' => $topicsCount,
        'detail' => 'Registros operacionais em current_topics',
        'href' => '/admin/contexts.php?date=' . $date,
    ],
    [
        'date' => $date,
        'name' => 'Priorização aplicada',
        'status' => $rankingCount > 0 ? 'Concluída' : 'Pendente',
        'count' => $rankingCount,
        'detail' => 'Registros salvos em daily_rankings',
        'href' => '/admin/priority.php?date=' . $date,
    ],
];

render_page_start('Coletas', 'collections', 'admin', 'Resumo do status das coletas e processamentos por data.');
?>
    <section class="panel">
        <form class="date-filter" method="get">
            <label>Data de referência <input type="date" name="date" value="<?= h($date) ?>"></label>
            <button type="submit">Ver status</button>
            <a class="button button-secondary" href="/admin/collections.php">Hoje</a>
        </form>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow"><?= h($date) ?></p>
                <h2>Status das coletas</h2>
            </div>
        </div>
        <div class="table-wrap table-wrap--plain">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Coleta</th>
                        <th>Status</th>
                        <th>Registros</th>
                        <th>Detalhe</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collectionRows as $row): ?>
                        <tr>
                            <td data-label="Data"><?= h($row['date']) ?></td>
                            <td data-label="Coleta"><strong><?= h($row['name']) ?></strong></td>
                            <td data-label="Status"><span class="status-badge <?= $row['count'] > 0 ? 'is-approved' : 'is-pending' ?>"><?= h($row['status']) ?></span></td>
                            <td data-label="Registros"><?= h((string) $row['count']) ?></td>
                            <td data-label="Detalhe"><?= h($row['detail']) ?></td>
                            <td data-label="Ações"><a class="button button-secondary is-compact" href="<?= h($row['href']) ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_page_end(); ?>
