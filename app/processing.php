<?php

function create_processing_run(string $processType, string $runDate, string $label): int
{
    $stmt = db()->prepare(
        'INSERT INTO processing_runs
         (process_type, run_date, status, current_label, summary_json, started_at, updated_at)
         VALUES (?, ?, "running", ?, "{}", NOW(), NOW())'
    );
    $stmt->execute([$processType, $runDate, $label]);
    return (int) db()->lastInsertId();
}

function processing_run_by_id(int $runId): ?array
{
    $stmt = db()->prepare('SELECT * FROM processing_runs WHERE id = ? LIMIT 1');
    $stmt->execute([$runId]);
    $run = $stmt->fetch();
    return $run ?: null;
}

function add_processing_log(int $runId, string $message, string $level = 'info', array $context = []): void
{
    $stmt = db()->prepare(
        'INSERT INTO processing_run_logs (run_id, level, message, context_json, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $runId,
        in_array($level, ['info', 'success', 'warning', 'error'], true) ? $level : 'info',
        mb_substr($message, 0, 500, 'UTF-8'),
        json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function update_processing_run(int $runId, string $status, string $label, array $summary = [], ?string $error = null): void
{
    $stmt = db()->prepare(
        'UPDATE processing_runs
         SET status = ?, current_label = ?, summary_json = ?, error_message = ?,
             finished_at = CASE WHEN ? IN ("done", "error") THEN NOW() ELSE finished_at END,
             updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([
        in_array($status, ['running', 'done', 'error'], true) ? $status : 'running',
        mb_substr($label, 0, 255, 'UTF-8'),
        json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $error,
        $status,
        $runId,
    ]);
}

function processing_logs_for_run(int $runId): array
{
    $stmt = db()->prepare(
        'SELECT id, level, message, context_json, created_at
         FROM processing_run_logs
         WHERE run_id = ?
         ORDER BY id ASC'
    );
    $stmt->execute([$runId]);
    return $stmt->fetchAll();
}

function processing_run_payload(int $runId): array
{
    $run = processing_run_by_id($runId);
    if (!$run) {
        return ['ok' => false, 'error' => 'Execucao nao encontrada.'];
    }

    $summary = json_decode((string) ($run['summary_json'] ?? '{}'), true);
    if (!is_array($summary)) {
        $summary = [];
    }

    return [
        'ok' => $run['status'] !== 'error',
        'run_id' => (int) $run['id'],
        'status' => $run['status'],
        'date' => $run['run_date'],
        'current_label' => $run['current_label'],
        'summary' => $summary,
        'error' => $run['error_message'],
        'logs' => processing_logs_for_run((int) $run['id']),
    ];
}
