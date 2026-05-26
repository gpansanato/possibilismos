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
        $runDate = $_POST['date'] ?? $today['date'];
        if (!is_string($runDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
            $runDate = $today['date'];
        }

        if (($_POST['reset_collectors'] ?? '') === '1') {
            reset_event_collector_statuses_for_date($runDate);
        }

        $dateParts = date_parts_from_run_date($runDate);
        $config = require __DIR__ . '/../app/config.php';
        $collectors = historical_event_collectors($config['sources']['historical'] ?? [], $config['sources']['wikimedia'] ?? []);
        ensure_event_collector_statuses($runDate, $collectors);

        $runId = create_processing_run('historical_events', $runDate, 'Coleta de eventos historicos iniciada');
        add_processing_log($runId, 'Execucao iniciada para ' . $runDate . '.', 'info', ['date' => $runDate]);
        add_processing_log($runId, count($collectors) . ' conectores preparados para a data selecionada.', 'info', ['connectors' => count($collectors)]);

        echo json_encode(processing_run_payload($runId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($mode === 'tick') {
        $runId = (int) ($_POST['run_id'] ?? $_GET['run_id'] ?? 0);
        $run = processing_run_by_id($runId);
        if (!$run) {
            throw new RuntimeException('Execucao nao encontrada.');
        }

        if ($run['status'] !== 'running') {
            echo json_encode(processing_run_payload($runId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $dateParts = date_parts_from_run_date((string) $run['run_date']);
        $result = collect_next_historical_event_connector($dateParts['month'], $dateParts['day'], (string) $run['run_date']);

        if (!empty($result['done'])) {
            $historicalSummary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
            $importSummary = event_import_summary_for_date((string) $run['run_date']);
            $summary = [
                'Encontrados' => $importSummary['total'],
                'Importados ou vinculados' => $importSummary['linked'],
                'Ignorados por duplicidade' => $importSummary['ignored'],
                'Eventos na base' => $historicalSummary['total'],
                'Aguardando enriquecimento' => $historicalSummary['not_enriched'],
                'Conectores concluidos' => ($result['completed_collectors'] ?? 0) . ' de ' . ($result['total_collectors'] ?? 0),
                'Falhas' => ($result['error_collectors'] ?? 0) + $importSummary['errors'],
            ];
            add_processing_log($runId, 'Execucao finalizada. A base da data foi consolidada.', 'success', $summary);
            update_processing_run($runId, 'done', 'Coleta finalizada', $summary);
            echo json_encode(processing_run_payload($runId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $collector = $result['collector'];
        $label = $collector['label'] ?? 'Conector historico';
        add_processing_log($runId, 'Inicio do conector: ' . $label . '.', 'info', [
            'group' => $collector['group_label'] ?? '',
            'source' => ($collector['source'] ?? '') . ' / ' . ($collector['source_variant'] ?? ''),
        ]);
        $level = ((int) ($result['failures'] ?? 0)) > 0 ? 'warning' : 'success';
        add_processing_log($runId, 'Finalizacao do conector: ' . $label . ' com ' . (int) $result['found'] . ' encontrados e ' . (int) $result['imported'] . ' importados ou vinculados.', $level, $result);
        update_processing_run($runId, 'running', 'Ultimo conector: ' . $label, [
            'Conectores concluidos' => ($result['completed_collectors'] ?? 0) . ' de ' . ($result['total_collectors'] ?? 0),
            'Pendentes' => $result['pending_collectors'] ?? 0,
            'Falhas' => $result['error_collectors'] ?? 0,
        ]);

        echo json_encode(processing_run_payload($runId), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    throw new RuntimeException('Modo de processamento invalido.');
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'status' => 'error',
        'error' => $e->getMessage(),
        'logs' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
