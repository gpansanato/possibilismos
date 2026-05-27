<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

$today = today_key();
$mode = $_POST['mode'] ?? $_GET['mode'] ?? '';

try {
    if ($mode === 'start') {
        $runDate = operational_valid_date($_POST['date'] ?? $today['date'], $today['date']);
        $processType = operational_process_type((string) ($_POST['process_type'] ?? $_POST['action'] ?? ''));

        if ($processType === 'historical_events' && ($_POST['reset_collectors'] ?? '') === '1') {
            reset_event_collector_statuses_for_date($runDate);
        }

        $runId = create_processing_run($processType, $runDate, operational_start_label($processType));
        update_processing_run_state($runId, operational_initial_state($processType));
        operational_prepare_process($runId, $processType, $runDate);

        operational_json_response($runId);
    }

    if ($mode === 'tick') {
        $runId = (int) ($_POST['run_id'] ?? $_GET['run_id'] ?? 0);
        $run = processing_run_by_id($runId);
        if (!$run) {
            throw new RuntimeException('Execucao nao encontrada.');
        }

        if ($run['status'] === 'running') {
            operational_tick($run);
        }

        operational_json_response($runId);
    }

    throw new RuntimeException('Modo de processamento invalido.');
} catch (Throwable $e) {
    $runId = (int) ($_POST['run_id'] ?? $_GET['run_id'] ?? 0);
    if ($runId > 0 && processing_run_by_id($runId)) {
        add_processing_log($runId, 'Falha no processamento: ' . $e->getMessage(), 'error');
        update_processing_run($runId, 'error', 'Processamento interrompido', ['Falhas' => 1], $e->getMessage());
        operational_json_response($runId);
    }

    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'error' => $e->getMessage(),
        'logs' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function operational_valid_date($value, string $fallback): string
{
    return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $fallback;
}

function operational_process_type(string $value): string
{
    $map = [
        'process_events' => 'historical_events',
        'process_enrichment' => 'event_enrichment',
        'process_context' => 'daily_context',
        'process_priority' => 'event_priority',
    ];
    $value = $map[$value] ?? $value;
    if (!in_array($value, ['historical_events', 'event_enrichment', 'daily_context', 'event_priority'], true)) {
        throw new RuntimeException('Processamento operacional invalido.');
    }

    return $value;
}

function operational_start_label(string $processType): string
{
    return [
        'historical_events' => 'Coleta de eventos historicos iniciada',
        'event_enrichment' => 'Enriquecimento de eventos iniciado',
        'daily_context' => 'Coleta de contexto iniciada',
        'event_priority' => 'Priorizacao de eventos iniciada',
    ][$processType];
}

function operational_initial_state(string $processType): array
{
    return [
        'historical_events' => ['step' => 'connectors'],
        'event_enrichment' => ['step' => 'batch', 'iterations' => 0],
        'daily_context' => ['step' => 'news'],
        'event_priority' => ['step' => 'prepare'],
    ][$processType];
}

function operational_prepare_process(int $runId, string $processType, string $runDate): void
{
    $dateParts = date_parts_from_run_date($runDate);

    if ($processType === 'historical_events') {
        $config = require __DIR__ . '/../app/config.php';
        $collectors = historical_event_collectors($config['sources']['historical'] ?? [], $config['sources']['wikimedia'] ?? []);
        ensure_event_collector_statuses($runDate, $collectors);
        add_processing_log($runId, 'Coleta de eventos historicos preparada para ' . $runDate . '.', 'info', ['data' => $runDate]);
        add_processing_log($runId, count($collectors) . ' conectores historicos disponiveis para execucao.', 'info', ['conectores' => count($collectors)]);
        update_processing_run($runId, 'running', 'Fila de conectores preparada', ['Conectores concluidos' => '0 de ' . count($collectors)]);
        return;
    }

    if ($processType === 'event_enrichment') {
        $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
        add_processing_log($runId, 'Enriquecimento completo preparado para todos os grupos ativos.', 'info', [
            'eventos na data' => $summary['total'],
            'sem enriquecimento marcado' => $summary['not_enriched'],
        ]);
        update_processing_run($runId, 'running', 'Aguardando primeiro lote de enriquecimento', [
            'Eventos na data' => $summary['total'],
            'Aguardando enriquecimento' => $summary['not_enriched'],
        ]);
        return;
    }

    if ($processType === 'daily_context') {
        add_processing_log($runId, 'Coleta de contexto preparada para noticias, tendencias e topicos operacionais.', 'info', ['data' => $runDate]);
        update_processing_run($runId, 'running', 'Aguardando coleta de noticias', ['Etapa atual' => 'Noticias']);
        return;
    }

    add_processing_log($runId, 'Priorizacao preparada para cruzar eventos historicos com contextos coletados.', 'info', ['data' => $runDate]);
    update_processing_run($runId, 'running', 'Aguardando aplicacao dos criterios', ['Etapa atual' => 'Priorizacao']);
}

function operational_tick(array $run): void
{
    $processType = (string) $run['process_type'];
    if ($processType === 'historical_events') {
        operational_tick_historical_events($run);
        return;
    }
    if ($processType === 'event_enrichment') {
        operational_tick_event_enrichment($run);
        return;
    }
    if ($processType === 'daily_context') {
        operational_tick_daily_context($run);
        return;
    }
    if ($processType === 'event_priority') {
        operational_tick_event_priority($run);
        return;
    }

    throw new RuntimeException('Tipo de processamento sem executor.');
}

function operational_tick_historical_events(array $run): void
{
    $runId = (int) $run['id'];
    $runDate = (string) $run['run_date'];
    $dateParts = date_parts_from_run_date($runDate);
    $result = collect_next_historical_event_connector($dateParts['month'], $dateParts['day'], $runDate);

    if (!empty($result['done'])) {
        $historicalSummary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
        $importSummary = event_import_summary_for_date($runDate);
        $summary = [
            'Encontrados' => $importSummary['total'],
            'Importados ou vinculados' => $importSummary['linked'],
            'Ignorados por duplicidade' => $importSummary['ignored'],
            'Eventos na base' => $historicalSummary['total'],
            'Aguardando enriquecimento' => $historicalSummary['not_enriched'],
            'Conectores concluidos' => ($result['completed_collectors'] ?? 0) . ' de ' . ($result['total_collectors'] ?? 0),
            'Falhas' => ($result['error_collectors'] ?? 0) + $importSummary['errors'],
        ];
        add_processing_log($runId, 'Coleta finalizada. Eventos canonicos e imports foram consolidados.', 'success', $summary);
        update_processing_run($runId, 'done', 'Coleta de eventos finalizada', $summary);
        return;
    }

    $collector = $result['collector'];
    $label = $collector['label'] ?? 'Conector historico';
    add_processing_log($runId, 'Inicio do conector: ' . $label . '.', 'info', [
        'grupo' => $collector['group_label'] ?? '',
        'fonte' => ($collector['source'] ?? '') . ' / ' . ($collector['source_variant'] ?? ''),
    ]);
    $level = ((int) ($result['failures'] ?? 0)) > 0 ? 'warning' : 'success';
    add_processing_log($runId, 'Conector finalizado: ' . $label . ' retornou ' . (int) $result['found'] . ' candidatos e ' . (int) $result['imported'] . ' registros importados ou vinculados.', $level, [
        'encontrados' => (int) ($result['found'] ?? 0),
        'importados' => (int) ($result['imported'] ?? 0),
        'enriquecimentos' => (int) ($result['enriched'] ?? 0),
        'falhas' => (int) ($result['failures'] ?? 0),
    ]);
    update_processing_run($runId, 'running', 'Ultimo conector: ' . $label, [
        'Conectores concluidos' => ($result['completed_collectors'] ?? 0) . ' de ' . ($result['total_collectors'] ?? 0),
        'Pendentes' => $result['pending_collectors'] ?? 0,
        'Falhas' => $result['error_collectors'] ?? 0,
    ]);
}

function operational_tick_event_enrichment(array $run): void
{
    $runId = (int) $run['id'];
    $runDate = (string) $run['run_date'];
    $dateParts = date_parts_from_run_date($runDate);
    $state = processing_run_state($run);
    $iterations = (int) ($state['iterations'] ?? 0) + 1;

    add_processing_log($runId, 'Inicio do lote de enriquecimento ' . $iterations . ': todos os grupos ativos serao avaliados para os eventos pendentes.', 'info');
    $result = enrich_historical_events_for_day($dateParts['month'], $dateParts['day'], 'all');
    $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
    $runSummary = [
        'Eventos avaliados' => $result['evaluated'],
        'Processados nesta chamada' => $result['processed_events'],
        'Eventos enriquecidos' => $summary['enriched'],
        'Enriquecimentos salvos' => $summary['enrichment_records'],
        'Ainda sem enriquecimento marcado' => $summary['not_enriched'],
        'Sem fonte suficiente' => $result['without_source'],
        'Sem resultado' => $result['without_results'],
        'Falhas' => $result['failures'],
    ];

    add_processing_log($runId, 'Lote de enriquecimento ' . $iterations . ' finalizado: ' . (int) $result['processed_events'] . ' eventos processados, ' . (int) $result['enriched_events'] . ' enriquecidos e ' . (int) $result['saved_enrichments'] . ' registros de apoio salvos.', ((int) $result['failures']) > 0 ? 'warning' : 'success', [
        'grupo' => $result['group_label'],
        'pendentes neste tipo' => $result['remaining_events'],
        'fontes' => operational_source_stats_text($result['source_stats'] ?? []),
    ]);

    if ((int) $result['remaining_events'] <= 0 || (int) $result['processed_events'] === 0) {
        add_processing_log($runId, 'Enriquecimento finalizado. Nao ha novos lotes pendentes para os grupos ativos neste momento.', 'success', $runSummary);
        update_processing_run($runId, 'done', 'Enriquecimento finalizado', $runSummary);
        return;
    }

    update_processing_run_state($runId, ['step' => 'batch', 'iterations' => $iterations]);
    update_processing_run($runId, 'running', 'Lote ' . $iterations . ' concluido; preparando proximo lote', $runSummary);
}

function operational_tick_daily_context(array $run): void
{
    $runId = (int) $run['id'];
    $runDate = (string) $run['run_date'];
    $state = processing_run_state($run);
    $step = (string) ($state['step'] ?? 'news');

    if ($step === 'news') {
        add_processing_log($runId, 'Inicio da coleta de noticias: fontes ativas serao consultadas para a data selecionada.', 'info');
        $news = collect_daily_news_topics($runDate);
        add_processing_log($runId, 'Coleta de noticias finalizada com ' . count($news) . ' registros persistidos ou atualizados.', 'success', ['noticias' => count($news)]);
        update_processing_run_state($runId, ['step' => 'trends']);
        update_processing_run($runId, 'running', 'Noticias coletadas; aguardando tendencias', ['Noticias' => count($news)]);
        return;
    }

    if ($step === 'trends') {
        add_processing_log($runId, 'Inicio da coleta de tendencias: sinais do dia serao consultados e higienizados.', 'info');
        $trends = collect_daily_trend_topics($runDate);
        add_processing_log($runId, 'Coleta de tendencias finalizada com ' . count($trends) . ' registros persistidos ou atualizados.', 'success', ['tendencias' => count($trends)]);
        update_processing_run_state($runId, ['step' => 'summary']);
        update_processing_run($runId, 'running', 'Tendencias coletadas; consolidando topicos', ['Tendencias' => count($trends)]);
        return;
    }

    $newsCount = collected_contexts_count_for_date($runDate, 'news');
    $trendCount = collected_contexts_count_for_date($runDate, 'trend');
    $topics = current_topics_count_for_date($runDate);
    $summary = [
        'Noticias coletadas' => $newsCount,
        'Tendencias coletadas' => $trendCount,
        'Topicos operacionais' => $topics,
        'Contextos totais' => $newsCount + $trendCount,
        'Falhas' => 0,
    ];
    add_processing_log($runId, 'Contexto consolidado. Noticias, tendencias e topicos estao disponiveis para priorizacao.', 'success', $summary);
    update_processing_run($runId, 'done', 'Coleta de contexto finalizada', $summary);
}

function operational_tick_event_priority(array $run): void
{
    $runId = (int) $run['id'];
    $runDate = (string) $run['run_date'];
    $dateParts = date_parts_from_run_date($runDate);
    $summaryBefore = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
    $contextTotal = collected_contexts_count_for_date($runDate);
    $topics = current_topics_count_for_date($runDate);

    add_processing_log($runId, 'Inicio da priorizacao: ' . $summaryBefore['total'] . ' eventos serao cruzados com ' . $contextTotal . ' contextos e ' . $topics . ' topicos.', 'info', [
        'eventos' => $summaryBefore['total'],
        'contextos' => $contextTotal,
        'topicos' => $topics,
    ]);
    $ranked = apply_daily_priority_score($runDate);
    $summary = [
        'Eventos avaliados' => $summaryBefore['total'],
        'Contextos considerados' => $contextTotal,
        'Topicos operacionais' => $topics,
        'Rankings gerados' => count($ranked),
        'Falhas' => 0,
    ];
    add_processing_log($runId, 'Priorizacao finalizada: ' . count($ranked) . ' eventos receberam score, motivos e resumo contextual.', 'success', $summary);
    update_processing_run($runId, 'done', 'Priorizacao finalizada', $summary);
}

function operational_json_response(int $runId): void
{
    $payload = processing_run_payload($runId);
    $payload['tableRows'] = operational_status_rows_html((string) ($payload['date'] ?? today_key()['date']));
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function operational_status_rows_html(string $date): string
{
    $dateParts = date_parts_from_run_date($date);
    $eventCounts = events_count_by_review_status($dateParts['month'], $dateParts['day']);
    $historicalSummary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
    $importSummary = event_import_summary_for_date($date);
    $newsCount = collected_contexts_count_for_date($date, 'news');
    $trendCount = collected_contexts_count_for_date($date, 'trend');
    $topicsCount = current_topics_count_for_date($date);
    $rankingCount = rankings_count_for_date($date);
    $rows = [
        [
            'date' => $date,
            'name' => 'Coleta e normalizacao dos eventos historicos',
            'status' => $historicalSummary['total'] > 0 ? 'Concluida' : 'Pendente',
            'count' => $historicalSummary['total'],
            'detail' => $eventCounts['pending'] . ' nao publicados, ' . $eventCounts['approved'] . ' publicados, ' . $eventCounts['rejected'] . ' reprovados; ' . $importSummary['linked'] . ' imports vinculados',
            'href' => '/admin/events.php?date=' . $date,
        ],
        [
            'date' => $date,
            'name' => 'Enriquecimento dos eventos historicos',
            'status' => $historicalSummary['total'] === 0 ? 'Pendente' : ($historicalSummary['not_enriched'] === 0 ? 'Concluida' : ($historicalSummary['enriched'] > 0 ? 'Parcial' : 'Pendente')),
            'count' => $historicalSummary['enriched'],
            'detail' => $historicalSummary['enriched'] . ' eventos enriquecidos, ' . $historicalSummary['not_enriched'] . ' aguardando enriquecimento; ' . $historicalSummary['enrichment_records'] . ' registros de apoio',
            'href' => '/admin/events.php?date=' . $date,
        ],
        [
            'date' => $date,
            'name' => 'Coleta de noticias e tendencias',
            'status' => ($newsCount + $trendCount) > 0 ? 'Concluida' : 'Pendente',
            'count' => $newsCount + $trendCount,
            'detail' => $newsCount . ' noticias, ' . $trendCount . ' tendencias e ' . $topicsCount . ' topicos operacionais',
            'href' => '/admin/contexts.php?date=' . $date,
        ],
        [
            'date' => $date,
            'name' => 'Aplicacao dos criterios de priorizacao',
            'status' => $rankingCount > 0 ? 'Concluida' : 'Pendente',
            'count' => $rankingCount,
            'detail' => 'Registros salvos em daily_rankings',
            'href' => '/admin/priority.php?date=' . $date,
        ],
    ];

    ob_start();
    foreach ($rows as $row): ?>
        <tr>
            <td data-label="Data"><?= h($row['date']) ?></td>
            <td data-label="Processamento"><strong><?= h($row['name']) ?></strong></td>
            <td data-label="Status"><span class="status-badge <?= $row['count'] > 0 ? 'is-approved' : 'is-pending' ?>"><?= h($row['status']) ?></span></td>
            <td data-label="Registros"><?= h((string) $row['count']) ?></td>
            <td data-label="Detalhe"><?= h($row['detail']) ?></td>
            <td data-label="Acoes"><a class="button button-secondary is-compact" href="<?= h($row['href']) ?>">Abrir</a></td>
        </tr>
    <?php endforeach;

    return trim((string) ob_get_clean());
}

function operational_source_stats_text(array $sourceStats): string
{
    if (!$sourceStats) {
        return 'nenhuma fonte externa consultada';
    }

    $parts = [];
    foreach ($sourceStats as $source => $stats) {
        $parts[] = $source . ': ' . (int) ($stats['attempted'] ?? 0) . ' consultas, ' . (int) ($stats['saved'] ?? 0) . ' salvos';
    }

    return implode(' | ', array_slice($parts, 0, 4));
}
