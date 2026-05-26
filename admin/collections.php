<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$date = $_GET['date'] ?? $today['date'];
if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today['date'];
}

$message = null;
$error = null;
$processTitle = null;
$processDescription = null;
$processSteps = [];
$processSummary = [];
$isAsyncRequest = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAsyncRequest = ($_POST['async'] ?? '') === '1' || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    $action = $_POST['action'] ?? '';
    $actionDate = $_POST['date'] ?? $date;
    if (!is_string($actionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
        $actionDate = $today['date'];
    }
    $date = $actionDate;
    $dateParts = date_parts_from_run_date($actionDate);
    $startedAt = microtime(true);
    $startedLabel = date('H:i:s');
    $failedStep = 'Preparando execucao';

    try {
        if ($action === 'process_events') {
            $failedStep = 'Consultando fonte de dados';
            $resetCollectors = ($_POST['reset_collectors'] ?? '') === '1';
            if ($resetCollectors) {
                reset_event_collector_statuses_for_date($actionDate);
            }
            $result = collect_historical_events_for_day($dateParts['month'], $dateParts['day'], $actionDate);
            $failedStep = 'Atualizando resumo da coleta';
            $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
            $imports = event_import_summary_for_date($actionDate);
            $processTitle = 'Coleta de eventos historicos';
            $processDescription = $resetCollectors
                ? 'A fila de coletores da data foi reiniciada e a coleta foi executada novamente. O limite operacional considera apenas tempo de execucao.'
                : 'Coleta, normalizacao e deduplicacao dos fatos historicos da data selecionada. O limite operacional considera apenas tempo de execucao; coletores ja concluidos sao pulados em novas execucoes.';
            $processSteps = [
                collection_process_step('Execucao iniciada', 'A coleta historica foi iniciada para a data selecionada.', 'Data: ' . $actionDate . '; modo: ' . ($resetCollectors ? 'recoleta da data' : 'continuidade da fila')),
            ];
            $processSteps = array_merge($processSteps, collection_event_collector_log_steps($result['collectors'] ?? []));
            $processSteps[] = collection_process_step('Consolidacao da coleta', 'Os candidatos retornados pelos conectores foram normalizados, deduplicados e vinculados aos eventos canonicos durante a execucao.', $imports['linked'] . ' imports vinculados; ' . $imports['ignored'] . ' ignorados por duplicidade');
            $processSteps[] = collection_process_step('Resumo da base do dia', 'A base de eventos historicos da data foi recalculada depois da coleta.', $summary['total'] . ' eventos historicos; ' . $summary['not_enriched'] . ' aguardando enriquecimento');
            $processSteps[] = collection_process_step('Fila de conectores atualizada', 'O controle de coletores foi atualizado para permitir continuidade em nova execucao quando houver pendencias.', ($result['completed_collectors'] ?? 0) . ' de ' . ($result['total_collectors'] ?? 0) . ' conectores concluidos');
            $processSteps[] = collection_process_step('Execucao finalizada', 'O servidor concluiu a rodada de coleta e devolveu o resumo para a interface.', $imports['errors'] . ' falhas registradas');
            $processSummary = collection_process_summary($startedAt, $startedLabel, [
                'Encontrados' => $result['found'] ?? $result['imported'],
                'Importados' => $result['imported'],
                'Novos' => $imports['linked'],
                'Atualizados' => $imports['linked'],
                'Ignorados por duplicidade' => $imports['ignored'],
                'Aguardando enriquecimento' => $summary['not_enriched'],
                'Falhas' => ($result['failures'] ?? 0) + $imports['errors'],
                'Coletores executados agora' => $result['processed_collectors'] ?? 0,
                'Coletores ja concluidos antes' => $result['already_completed_collectors'] ?? 0,
                'Coletores concluidos no total' => ($result['completed_collectors'] ?? 0) . ' de ' . ($result['total_collectors'] ?? 0),
                'Ainda falta coletar' => ($result['pending_collectors'] ?? 0) > 0 ? ($result['pending_collectors'] . ' coletores: ' . implode(', ', array_slice($result['pending_collector_labels'] ?? [], 0, 4))) : 'nenhum coletor pendente',
                'Coletores com erro' => $result['error_collectors'] ?? 0,
                'Limite operacional' => !empty($result['halted_by_budget']) ? 'tempo atingido (' . ($result['max_duration_seconds'] ?? 0) . 's); execute novamente para continuar a fila' : 'tempo nao atingido',
                'Modo de execucao' => $resetCollectors ? 'fila reiniciada para a data' : 'continua apenas coletores pendentes',
            ]);
            $message = 'Coleta de eventos historicos concluida.';
        } elseif ($action === 'process_enrichment') {
            $enrichmentGroup = normalize_historical_enrichment_group((string) ($_POST['enrichment_group'] ?? 'light'));
            $failedStep = 'Executando enriquecimento';
            $result = enrich_historical_events_for_day($dateParts['month'], $dateParts['day'], $enrichmentGroup);
            $failedStep = 'Atualizando resumo da coleta';
            $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
            $processTitle = 'Processamento 2: enriquecimento de eventos';
            $processDescription = !empty($result['halted_by_budget'])
                ? 'Enriquecimento parcial concluido para evitar timeout do servidor. Execute novamente para continuar os eventos pendentes. Tipo aplicado: ' . $result['group_label'] . '.'
                : 'Enriquecimento coletivo dos fatos historicos da data, sem reexecutar a coleta principal. Tipo aplicado: ' . $result['group_label'] . '.';
            $processSteps = [
                collection_process_step('Preparar eventos para enriquecimento', 'Seleciona todos os eventos historicos do dia para o tipo de enriquecimento solicitado.', $result['evaluated'] . ' eventos avaliados; limite de ' . $result['max_events_per_run'] . ' por execucao'),
                collection_process_step('Verificar enriquecimentos existentes', 'Eventos ja cobertos por este tipo sao contabilizados sem nova chamada externa.', $result['already_enriched'] . ' eventos ja enriquecidos'),
                collection_process_step('Executar enriquecimento solicitado', 'Aplica o grupo selecionado apenas no lote permitido pela execucao atual.', $result['processed_events'] . ' eventos processados; ' . collection_enrichment_source_stats_text($result['source_stats'] ?? [])),
                collection_process_step('Atualizar eventos enriquecidos', 'Marca eventos com enriquecimento salvo e preserva os que nao retornaram resultado.', $result['enriched_events'] . ' eventos enriquecidos'),
                collection_process_step('Registrar itens sem fonte ou sem resultado', 'Eventos sem fonte suficiente ou sem retorno aplicavel ficam registrados para evitar chamadas repetidas na mesma fonte.', $result['without_source'] . ' sem fonte; ' . $result['without_results'] . ' sem resultado'),
                collection_process_step('Atualizar resumo operacional do enriquecimento', 'Contadores recalculados para a tabela de status.', $summary['enrichment_records'] . ' enriquecimentos totais'),
                collection_process_step('Finalizar execucao', 'Resumo final devolvido para a interface.', $result['failures'] . ' falhas registradas; ' . $result['remaining_events'] . ' eventos ainda pendentes neste tipo'),
            ];
            $processSummary = collection_process_summary($startedAt, $startedLabel, [
                'Tipo aplicado' => $result['group_label'],
                'Eventos avaliados' => $result['evaluated'],
                'Ja enriquecidos' => $result['already_enriched'],
                'Processados nesta execucao' => $result['processed_events'],
                'Eventos enriquecidos' => $result['enriched_events'],
                'Enriquecimentos salvos' => $result['saved_enrichments'],
                'Ainda falta enriquecer' => $result['remaining_events'] > 0 ? $result['remaining_events'] . ' eventos; execute novamente para continuar' : 'nenhum evento pendente neste tipo',
                'Limite operacional' => !empty($result['halted_by_budget']) ? 'atingido; execucao encerrada de forma controlada' : 'nao atingido',
                'Sem fonte suficiente' => $result['without_source'],
                'Sem resultado' => $result['without_results'],
                'Falhas' => $result['failures'],
                'Fontes consultadas' => collection_enrichment_source_stats_text($result['source_stats'] ?? []),
            ]);
            $message = !empty($result['halted_by_budget'])
                ? 'Enriquecimento parcial concluido. Ainda ha eventos pendentes para nova execucao.'
                : 'Processamento de enriquecimento concluido.';
        } elseif ($action === 'process_context') {
            $failedStep = 'Consultando fonte de dados';
            $news = collect_daily_news_topics($actionDate);
            $failedStep = 'Recebendo registros';
            $trends = collect_daily_trend_topics($actionDate);
            $failedStep = 'Atualizando resumo da coleta';
            $topics = current_topics_count_for_date($actionDate);
            $processTitle = 'Processamento 3: contexto do dia';
            $processDescription = 'Coleta e higienizacao integrada de noticias e tendencias usadas como insumos contextuais.';
            $processSteps = [
                collection_process_step('Preparar execucao para a data selecionada', 'Parametros de data validados e fontes de contexto identificadas.', '1 data processada'),
                collection_process_step('Consultar feeds e APIs de noticias configuradas', 'Noticias do dia coletadas como insumo editorial.', count($news) . ' noticias persistidas'),
                collection_process_step('Consultar fontes de tendencias configuradas', 'Sinais de tendencia coletados nas fontes habilitadas.', count($trends) . ' tendencias persistidas'),
                collection_process_step('Derivar tendencias a partir das noticias quando necessario', 'Tendencias auxiliares geradas quando a fonte externa nao retornou itens suficientes.', count($trends) . ' tendencias disponiveis'),
                collection_process_step('Extrair e normalizar palavras-chave', 'Termos e topicos higienizados para uso na priorizacao.', $topics . ' topicos operacionais'),
                collection_process_step('Verificar duplicidades na base higienizada', 'Itens repetidos foram evitados antes da persistencia final.', 'Deduplicacao aplicada na persistencia'),
                collection_process_step('Salvar noticias, tendencias e topicos operacionais', 'Contextos persistidos na base operacional.', (count($news) + count($trends)) . ' contextos salvos'),
                collection_process_step('Atualizar resumo operacional do contexto', 'Contadores recalculados para a tabela de status.', $topics . ' topicos disponiveis'),
                collection_process_step('Finalizar execucao', 'Resumo final devolvido para a interface.', '0 falhas registradas'),
            ];
            $processSummary = collection_process_summary($startedAt, $startedLabel, [
                'Noticias encontradas' => count($news),
                'Tendencias encontradas' => count($trends),
                'Topicos operacionais' => $topics,
                'Falhas' => 0,
            ]);
            $message = 'Processamento de contexto concluido.';
        } elseif ($action === 'process_priority') {
            $failedStep = 'Aplicando criterios de priorizacao';
            $ranked = apply_daily_priority_score($actionDate);
            $failedStep = 'Atualizando resumo da coleta';
            $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
            $contextTotal = collected_contexts_count_for_date($actionDate);
            $topics = current_topics_count_for_date($actionDate);
            $processTitle = 'Processamento 4: priorizacao de eventos';
            $processDescription = 'Aplicacao dos criterios editoriais sobre eventos historicos, noticias, tendencias e topicos de contexto.';
            $processSteps = [
                collection_process_step('Preparar execucao para a data selecionada', 'Parametros de data validados e criterios de priorizacao carregados.', '1 data processada'),
                collection_process_step('Carregar eventos historicos coletados', 'Eventos historicos disponiveis para o dia foram selecionados.', $summary['total'] . ' eventos avaliaveis'),
                collection_process_step('Carregar noticias, tendencias e topicos higienizados', 'Sinais de contexto foram reunidos como apoio editorial.', $contextTotal . ' contextos; ' . $topics . ' topicos'),
                collection_process_step('Aplicar relevancia historica base', 'Eventos receberam pontuacao inicial por relevancia historica.', $summary['total'] . ' eventos pontuados'),
                collection_process_step('Calcular conexoes com noticias e tendencias', 'Termos e temas foram comparados com o contexto do dia.', $contextTotal . ' contextos considerados'),
                collection_process_step('Aplicar bonus, categoria e diversidade', 'Ajustes editoriais foram aplicados ao ranking final.', count($ranked) . ' eventos ajustados'),
                collection_process_step('Salvar score, motivos e resumo contextual', 'Score, motivos e justificativas foram persistidos.', count($ranked) . ' rankings gerados'),
                collection_process_step('Atualizar status da priorizacao', 'Contadores recalculados para a tabela de status.', count($ranked) . ' rankings consolidados'),
                collection_process_step('Finalizar execucao', 'Resumo final devolvido para a interface.', '0 falhas registradas'),
            ];
            $processSummary = collection_process_summary($startedAt, $startedLabel, [
                'Eventos avaliados' => $summary['total'],
                'Contextos considerados' => $contextTotal,
                'Rankings gerados' => count($ranked),
                'Falhas' => 0,
            ]);
            $message = count($ranked) . ' eventos priorizados para ' . $actionDate . '.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $processTitle = $processTitle ?: collection_action_label((string) $action);
        $processDescription = 'A execucao foi interrompida antes da finalizacao.';
        $processSummary = collection_process_summary($startedAt, $startedLabel, [
            'Registros encontrados' => 0,
            'Novos ou atualizados' => 0,
            'Ignorados por duplicidade' => 0,
            'Falhas' => 1,
        ]);
        $processSummary['Etapa com erro'] = $failedStep;
        $processSummary['Mensagem'] = $error;
    }

    if ($isAsyncRequest) {
        $dateParts = date_parts_from_run_date($date);
        $eventCounts = events_count_by_review_status($dateParts['month'], $dateParts['day']);
        $historicalSummary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
        $importSummary = event_import_summary_for_date($date);
        $newsCount = collected_contexts_count_for_date($date, 'news');
        $trendCount = collected_contexts_count_for_date($date, 'trend');
        $topicsCount = current_topics_count_for_date($date);
        $rankingCount = rankings_count_for_date($date);
        $collectionRows = collection_status_rows($date, $eventCounts, $historicalSummary, $importSummary, $newsCount, $trendCount, $topicsCount, $rankingCount);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => $error === null,
            'message' => $message,
            'error' => $error,
            'date' => $date,
            'title' => $processTitle,
            'description' => $processDescription,
            'steps' => $processSteps,
            'summary' => $processSummary,
            'tableRows' => collection_status_rows_html($collectionRows),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

$dateParts = date_parts_from_run_date($date);
$eventCounts = events_count_by_review_status($dateParts['month'], $dateParts['day']);
$historicalSummary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
$importSummary = event_import_summary_for_date($date);
$newsCount = collected_contexts_count_for_date($date, 'news');
$trendCount = collected_contexts_count_for_date($date, 'trend');
$topicsCount = current_topics_count_for_date($date);
$rankingCount = rankings_count_for_date($date);

$collectionRows = collection_status_rows($date, $eventCounts, $historicalSummary, $importSummary, $newsCount, $trendCount, $topicsCount, $rankingCount);
$collectionFlowSteps = collection_flow_steps();

render_page_start('Coletas', 'collections', 'admin', 'Home operacional para executar e acompanhar os processamentos por data.');
?>
    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

    <section class="panel collection-command-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Processamentos</p>
                <h2>Executar fluxo operacional</h2>
                <p>Defina a data de referencia e execute uma das etapas operacionais. A configuracao das fontes fica separada na tela Fontes.</p>
            </div>
        </div>
        <form class="date-filter" method="get">
            <label>Data de referencia <input type="date" name="date" id="collection-date-input" value="<?= h($date) ?>"></label>
            <button type="submit">Ver status</button>
            <a class="button button-secondary" href="/admin/collections.php">Hoje</a>
        </form>
        <form class="actions" method="post" id="collection-process-form" action="/admin/collections.php">
            <input type="hidden" name="date" id="collection-process-date" value="<?= h($date) ?>">
            <div class="process-action-group">
            <button name="action" value="process_events" type="submit" data-process-label="Coleta de eventos historicos">Coletar eventos historicos</button>
                <button class="button-secondary" name="action" value="process_events" type="submit" data-process-label="Recoleta de eventos historicos" data-reset-collectors="1">Recoletar eventos do dia</button>
                <button class="button-secondary" name="action" value="process_context" type="submit" data-process-label="Processamento 3: contexto do dia">Processar contexto do dia</button>
                <button class="button-secondary" name="action" value="process_priority" type="submit" data-process-label="Processamento 4: priorizacao de eventos">Aplicar priorizacao</button>
            </div>
            <input type="hidden" name="reset_collectors" value="0">
            <div class="enrichment-command">
                <div>
                    <strong>Enriquecimento dos eventos historicos</strong>
                    <p>Escolha o tipo de fonte complementar que sera aplicado aos eventos da data selecionada.</p>
                    <p><?= h((string) $historicalSummary['total']) ?> eventos na data; <?= h((string) $historicalSummary['not_enriched']) ?> ainda sem enriquecimento marcado.</p>
                </div>
                <label>Tipo de enriquecimento
                    <select name="enrichment_group">
                        <?php foreach (historical_available_enrichment_group_labels() as $groupKey => $groupLabel): ?>
                            <option value="<?= h($groupKey) ?>"><?= h($groupLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="button-secondary" name="action" value="process_enrichment" type="submit" data-process-label="Processamento 2: enriquecimento de eventos">Enriquecer eventos</button>
            </div>
        </form>
    </section>

    <section class="panel process-panel" id="collection-progress-panel" aria-live="polite">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Acompanhamento</p>
                <h2 id="collection-progress-title"><?= h($processTitle ?: 'Fluxo operacional por etapas') ?></h2>
                <p id="collection-progress-description"><?= h($processDescription ?: 'Selecione um processamento para acompanhar as etapas executadas para a data de referencia.') ?></p>
            </div>
        </div>
        <div class="progress-meter" aria-hidden="true">
            <span id="collection-progress-bar" style="width: <?= $processSteps ? '100' : '0' ?>%"></span>
        </div>
        <div class="process-meta" id="collection-progress-meta">
            <span id="collection-progress-status"><?= $processSteps ? 'Processamento concluido' : 'Aguardando execucao' ?></span>
            <span id="collection-progress-elapsed"><?= $processSteps && isset($processSummary['Duracao']) ? 'Duracao: ' . h((string) $processSummary['Duracao']) : 'Tempo decorrido: 0s' ?></span>
        </div>
        <div class="process-log" id="collection-progress-log">
            <?php if ($processSteps): ?>
                <?php foreach ($processSteps as $index => $step): ?>
                    <div class="process-log__item is-done">
                        <span>Concluido:</span> <strong><?= h($step['title']) ?></strong>
                        <p><?= h($step['description']) ?> <?= h($step['result']) ?></p>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="process-log__empty">Nenhum processamento em execucao. Ao iniciar um fluxo, as atualizacoes aparecerao aqui em ordem.</p>
            <?php endif; ?>
        </div>
        <div class="process-summary" id="collection-process-summary">
            <?php if ($processSummary): ?>
                <?php foreach ($processSummary as $label => $value): ?>
                    <div><span><?= h((string) $label) ?></span><strong><?= h((string) $value) ?></strong></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow" id="collection-status-date"><?= h($date) ?></p>
                <h2>Status dos processamentos</h2>
            </div>
        </div>
        <div class="table-wrap table-wrap--plain">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Processamento</th>
                        <th>Status</th>
                        <th>Registros</th>
                        <th>Detalhe</th>
                        <th>Acoes</th>
                    </tr>
                </thead>
                <tbody id="collection-status-rows">
                    <?= collection_status_rows_html($collectionRows) ?>
                </tbody>
            </table>
        </div>
    </section>
    <script>
    (function () {
        const form = document.getElementById('collection-process-form');
        const panel = document.getElementById('collection-progress-panel');
        const title = document.getElementById('collection-progress-title');
        const description = document.getElementById('collection-progress-description');
        const logEl = document.getElementById('collection-progress-log');
        const summaryEl = document.getElementById('collection-process-summary');
        const progressBar = document.getElementById('collection-progress-bar');
        const progressStatus = document.getElementById('collection-progress-status');
        const progressElapsed = document.getElementById('collection-progress-elapsed');
        const statusRows = document.getElementById('collection-status-rows');
        const statusDate = document.getElementById('collection-status-date');
        const dateInput = document.getElementById('collection-date-input');
        const processDate = document.getElementById('collection-process-date');
        const resetCollectors = form.querySelector('[name="reset_collectors"]');
        if (!form || !panel || !logEl || !progressBar) {
            return;
        }

        const flowStepsByAction = <?= json_encode($collectionFlowSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let currentFlowSteps = [];
        let elapsedTimer = null;
        let startedAt = 0;

        function setButtons(disabled) {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = disabled;
                button.classList.toggle('is-loading', disabled);
            });
        }

        function renderLog(statuses) {
            const steps = (currentFlowSteps.length ? currentFlowSteps : [{ title: 'Preparando execucao' }]).map(normalizeStep);
            logEl.innerHTML = steps.map((step, index) => {
                const status = statuses[index] || 'pending';
                const statusLabel = status === 'done' ? 'Concluido' : status === 'running' ? 'Em execucao' : status === 'error' ? 'Erro' : 'Pendente';
                const detail = [step.description, step.result].filter(Boolean).join(' ');
                return '<div class="process-log__item is-' + status + '">' +
                    '<span>' + statusLabel + ':</span> ' +
                    '<strong>' + escapeHtml(step.title) + '</strong>' +
                    (detail ? '<p>' + escapeHtml(detail) + '</p>' : '') +
                    '</div>';
            }).join('');
            const done = statuses.filter((status) => status === 'done').length;
            const running = statuses.includes('running') ? 1 : 0;
            const progress = (done + running * 0.25) / steps.length * 100;
            progressBar.style.width = Math.min(96, progress) + '%';
        }

        function startVisualProgress(label, action) {
            startedAt = Date.now();
            const plannedSteps = action === 'process_events'
                ? []
                : (flowStepsByAction[action] || flowStepsByAction.default || ['Preparando execucao', 'Finalizando execucao']);
            currentFlowSteps = [{
                title: action === 'process_events' ? 'Coleta em andamento' : 'Aguardando resposta do servidor',
                description: action === 'process_events'
                    ? 'O servidor esta executando os conectores historicos. O log com cada conector e suas quantidades aparecera assim que a rodada terminar.'
                    : 'A requisicao esta ativa. Sem streaming de etapas, a interface nao confirma conclusoes parciais antes do retorno final.',
                result: ''
            }].concat(plannedSteps);
            title.textContent = label || 'Processamento em andamento';
            description.textContent = action === 'process_events'
                ? 'A coleta foi iniciada. Enquanto a requisicao estiver ativa, acompanhe o tempo decorrido; os detalhes reais chegam no retorno do servidor.'
                : 'A execucao foi enviada ao servidor. As etapas abaixo so serao marcadas como concluidas quando o servidor devolver o resumo real do processamento.';
            summaryEl.innerHTML = '';
            updateElapsed();
            progressStatus.textContent = 'Processamento em andamento';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            renderLog(currentFlowSteps.map((_, index) => index === 0 ? 'running' : 'pending'));
            elapsedTimer = window.setInterval(updateElapsed, 1000);
        }

        function finishVisualProgress(payload) {
            clearTimers();
            const steps = payload.steps && payload.steps.length ? payload.steps : currentFlowSteps;
            currentFlowSteps = steps.length ? steps : ['Finalizando execucao'];
            const statuses = currentFlowSteps.map(() => payload.ok ? 'done' : 'pending');
            if (!payload.ok) {
                statuses[0] = 'error';
            }
            title.textContent = payload.title || (payload.ok ? 'Processamento concluido' : 'Processamento interrompido');
            description.textContent = payload.ok
                ? (payload.description || payload.message || 'Execucao finalizada.')
                : (payload.error || 'Nao foi possivel concluir o processamento.');
            progressStatus.textContent = payload.ok ? 'Processamento concluido' : 'Processamento interrompido';
            updateElapsed(payload.summary && payload.summary.Duracao ? payload.summary.Duracao : null);
            renderLog(statuses);
            progressBar.style.width = payload.ok ? '100%' : '12%';
            progressBar.classList.toggle('is-error', !payload.ok);
            renderSummary(payload.summary || {});
            if (payload.tableRows && statusRows) {
                statusRows.innerHTML = payload.tableRows;
            }
            if (payload.date && statusDate) {
                statusDate.textContent = payload.date;
            }
        }

        function renderProcessingRun(payload) {
            title.textContent = payload.status === 'done' ? 'Coleta de eventos historicos concluida' : 'Coleta de eventos historicos em andamento';
            description.textContent = payload.current_label || 'Executando conectores historicos da data selecionada.';
            progressStatus.textContent = payload.status === 'done' ? 'Processamento concluido' : 'Processamento em andamento';
            updateElapsed();

            const logs = payload.logs || [];
            logEl.innerHTML = logs.length ? logs.map((log) => {
                const status = log.level === 'error' ? 'error' : log.level === 'success' ? 'done' : log.level === 'warning' ? 'running' : 'pending';
                return '<div class="process-log__item is-' + status + '">' +
                    '<span>' + escapeHtml(log.created_at || '') + '</span> ' +
                    '<strong>' + escapeHtml(log.message || '') + '</strong>' +
                    renderLogContext(log.context_json) +
                    '</div>';
            }).join('') : '<p class="process-log__empty">Preparando execucao da coleta historica.</p>';

            renderSummary(payload.summary || {});
            if (payload.status === 'done') {
                progressBar.style.width = '100%';
            } else {
                progressBar.style.width = progressFromSummary(payload.summary || {});
            }
            progressBar.classList.toggle('is-error', payload.status === 'error');
        }

        function renderLogContext(contextJson) {
            if (!contextJson) {
                return '';
            }
            try {
                const context = typeof contextJson === 'string' ? JSON.parse(contextJson) : contextJson;
                const entries = Object.entries(context || {}).slice(0, 5);
                if (!entries.length) {
                    return '';
                }
                return '<p>' + entries.map(([key, value]) => escapeHtml(key + ': ' + String(value))).join(' | ') + '</p>';
            } catch (error) {
                return '';
            }
        }

        function progressFromSummary(summary) {
            const value = String(summary['Conectores concluidos'] || '');
            const match = value.match(/(\d+)\s+de\s+(\d+)/);
            if (!match) {
                return '18%';
            }
            const done = parseInt(match[1], 10);
            const total = Math.max(1, parseInt(match[2], 10));
            return Math.max(8, Math.min(96, Math.round(done / total * 100))) + '%';
        }

        async function runHistoricalEventsProcess(submitter) {
            startedAt = Date.now();
            title.textContent = submitter.dataset.processLabel || 'Coleta de eventos historicos';
            description.textContent = 'Iniciando execucao dos conectores historicos. As notificacoes serao atualizadas a cada conector concluido.';
            summaryEl.innerHTML = '';
            progressBar.style.width = '8%';
            progressStatus.textContent = 'Iniciando processamento';
            logEl.innerHTML = '<div class="process-log__item is-running"><span>Iniciando:</span> <strong>Preparando execucao da coleta historica</strong><p>A data foi enviada ao servidor e a fila de conectores sera preparada.</p></div>';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            updateElapsed();
            elapsedTimer = window.setInterval(updateElapsed, 1000);

            const startData = new FormData();
            startData.set('mode', 'start');
            startData.set('date', dateInput && dateInput.value ? dateInput.value : processDate.value);
            startData.set('reset_collectors', submitter.dataset.resetCollectors === '1' ? '1' : '0');
            const startPayload = await postProcessRequest(startData);
            renderProcessingRun(startPayload);

            let payload = startPayload;
            while (payload.status === 'running') {
                const tickData = new FormData();
                tickData.set('mode', 'tick');
                tickData.set('run_id', payload.run_id);
                payload = await postProcessRequest(tickData);
                renderProcessingRun(payload);
                await new Promise((resolve) => window.setTimeout(resolve, 350));
            }

            clearTimers();
            setButtons(false);
        }

        async function postProcessRequest(formData) {
            const response = await fetch('/admin/historical-events-process.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });
            const payload = parseJsonResponse(await response.text());
            if (!payload.ok && payload.status === 'error') {
                throw new Error(payload.error || 'Falha no processamento.');
            }
            return payload;
        }

        function clearTimers() {
            window.clearInterval(elapsedTimer);
        }

        function updateElapsed(finalDuration) {
            if (!progressElapsed) {
                return;
            }
            if (finalDuration) {
                progressElapsed.textContent = 'Duracao: ' + finalDuration;
                return;
            }
            const seconds = startedAt ? Math.max(0, Math.floor((Date.now() - startedAt) / 1000)) : 0;
            progressElapsed.textContent = 'Tempo decorrido: ' + seconds + 's';
        }

        function normalizeStep(step) {
            if (typeof step === 'string') {
                return { title: step, description: '', result: '' };
            }

            return {
                title: step.title || 'Etapa operacional',
                description: step.description || '',
                result: step.result || ''
            };
        }

        function renderSummary(summary) {
            const entries = Object.entries(summary);
            summaryEl.innerHTML = entries.map(([label, value]) => {
                return '<div><span>' + escapeHtml(label) + '</span><strong>' + escapeHtml(String(value)) + '</strong></div>';
            }).join('');
        }

        function escapeHtml(value) {
            return value.replace(/[&<>"']/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            })[char]);
        }

        form.addEventListener('submit', async (event) => {
            const submitter = event.submitter;
            if (!submitter || !submitter.name) {
                return;
            }
            event.preventDefault();
            setButtons(true);
            progressBar.classList.remove('is-error');

            if (submitter.value === 'process_events') {
                try {
                    await runHistoricalEventsProcess(submitter);
                } catch (error) {
                    finishVisualProgress({
                        ok: false,
                        title: 'Falha na coleta historica',
                        error: error.message,
                        summary: { 'Falhas': 1, 'Mensagem': error.message }
                    });
                    setButtons(false);
                }
                return;
            }

            startVisualProgress(submitter.dataset.processLabel, submitter.value);

            if (resetCollectors) {
                resetCollectors.value = submitter.dataset.resetCollectors === '1' ? '1' : '0';
            }
            const formData = new FormData(form);
            if (dateInput && processDate) {
                processDate.value = dateInput.value || processDate.value;
            }
            formData.set(submitter.name, submitter.value);
            if (dateInput && dateInput.value) {
                formData.set('date', dateInput.value);
            }
            formData.set('async', '1');

            try {
                const endpoint = form.getAttribute('action') || '/admin/collections.php';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                });
                const responseText = await response.text();
                const payload = parseJsonResponse(responseText);
                finishVisualProgress(payload);
            } catch (error) {
                finishVisualProgress({
                    ok: false,
                    title: 'Falha de comunicacao',
                    error: 'A execucao pode ter sido interrompida ou a resposta do servidor nao foi compreendida: ' + error.message,
                    summary: { 'Falhas': 1, 'Mensagem': error.message }
                });
            } finally {
                clearTimers();
                setButtons(false);
            }
        });

        function parseJsonResponse(responseText) {
            try {
                return JSON.parse(responseText);
            } catch (error) {
                const start = responseText.indexOf('{');
                const end = responseText.lastIndexOf('}');
                if (start >= 0 && end > start) {
                    return JSON.parse(responseText.slice(start, end + 1));
                }
                throw new Error(responseText.replace(/\s+/g, ' ').trim().slice(0, 180) || 'resposta vazia');
            }
        }
    })();
    </script>
<?php render_page_end(); ?>

<?php
function collection_process_step(string $title, string $description, string $result): array
{
    return [
        'title' => $title,
        'description' => $description,
        'result' => $result,
    ];
}

function collection_process_summary(float $startedAt, string $startedLabel, array $items): array
{
    $finishedAt = microtime(true);
    $summary = $items;
    $summary['Inicio'] = $startedLabel;
    $summary['Fim'] = date('H:i:s');
    $summary['Duracao'] = number_format(max(0, $finishedAt - $startedAt), 1, ',', '.') . 's';

    return $summary;
}

function collection_enrichment_source_stats_text(array $sourceStats): string
{
    if (!$sourceStats) {
        return 'Nenhuma fonte externa consultada.';
    }

    $parts = [];
    foreach ($sourceStats as $source => $stats) {
        $parts[] = $source . ': ' .
            (int) ($stats['attempted'] ?? 0) . ' consultas, ' .
            (int) ($stats['saved'] ?? 0) . ' salvos, ' .
            (int) ($stats['empty'] ?? 0) . ' sem resultado, ' .
            (int) ($stats['skipped'] ?? 0) . ' ignorados, ' .
            (int) ($stats['errors'] ?? 0) . ' falhas';
    }

    return implode(' | ', $parts);
}

function collection_event_collector_log_steps(array $collectors): array
{
    if (!$collectors) {
        return [
            collection_process_step('Nenhum conector executado', 'A fila da data pode ja estar concluida; use Recoletar eventos do dia para reiniciar os coletores dessa data.', '0 conectores executados nesta requisicao'),
        ];
    }

    $steps = [];
    foreach ($collectors as $collector) {
        $label = $collector['label'] ?? (($collector['source'] ?? '') . ' / ' . ($collector['source_variant'] ?? ''));
        $groupLabel = $collector['group_label'] ?? 'Conectores historicos';
        $source = ($collector['source'] ?? '') . ' / ' . ($collector['source_variant'] ?? '');
        $found = (int) ($collector['found'] ?? 0);
        $imported = (int) ($collector['imported'] ?? 0);
        $enriched = (int) ($collector['enriched'] ?? 0);
        $failures = (int) ($collector['failures'] ?? 0);
        $duration = number_format((float) ($collector['duration'] ?? 0), 1, ',', '.') . 's';

        $steps[] = collection_process_step(
            'Inicio do conector: ' . $label,
            'Grupo operacional: ' . $groupLabel . '. O conector foi colocado em execucao para recuperar candidatos da fonte externa.',
            'Fonte: ' . $source
        );
        $steps[] = collection_process_step(
            'Finalizacao do conector: ' . $label,
            'Consulta, normalizacao e persistencia dos candidatos retornados por este conector foram encerradas.',
            $found . ' encontrados; ' . $imported . ' importados ou vinculados; ' . $enriched . ' enriquecimentos; ' . $failures . ' falhas; duracao ' . $duration
        );
    }

    return $steps;
}

function collection_action_label(string $action): string
{
    return [
        'process_events' => 'Coleta de eventos historicos',
        'process_enrichment' => 'Processamento 2: enriquecimento de eventos',
        'process_context' => 'Processamento 3: contexto do dia',
        'process_priority' => 'Processamento 4: priorizacao de eventos',
    ][$action] ?? 'Processamento operacional';
}

function collection_flow_steps(): array
{
    return [
        'process_events' => [
            collection_process_step('Coleta enviada ao servidor', 'A requisicao foi recebida. O servidor executara os conectores pendentes da data e retornara um log operacional ao final.', 'Aguardando retorno da execucao.'),
        ],
        'process_enrichment' => [
            collection_process_step('Preparar eventos para enriquecimento', 'Seleciona todos os eventos do dia para o tipo de enriquecimento escolhido.', 'Quantidade tratada: eventos avaliaveis.'),
            collection_process_step('Verificar enriquecimentos existentes', 'Identifica eventos ja cobertos por esse tipo para evitar chamadas externas desnecessarias.', 'Quantidade tratada: eventos ja enriquecidos.'),
            collection_process_step('Executar enriquecimento solicitado', 'Aplica o grupo selecionado: leve, documental, visual, geografico ou completo.', 'Quantidade tratada: fontes consultadas.'),
            collection_process_step('Salvar enriquecimentos obtidos', 'Persiste registros em event_enrichments e atualiza image_url/enriched_at quando aplicavel.', 'Quantidade tratada: enriquecimentos salvos.'),
            collection_process_step('Registrar eventos sem fonte ou sem resultado', 'Mantem eventos sem retorno disponiveis para nova tentativa.', 'Quantidade tratada: eventos sem enriquecimento.'),
            collection_process_step('Atualizar resumo operacional do enriquecimento', 'Recalcula contadores de eventos enriquecidos e pendentes.', 'Quantidade tratada: totais consolidados.'),
            collection_process_step('Finalizar execucao', 'Fecha o fluxo e devolve o resumo da execucao para a interface.', 'Quantidade tratada: resumo final.'),
        ],
        'process_context' => [
            collection_process_step('Preparar execucao para a data selecionada', 'Valida a data e identifica as fontes de contexto ativas.', 'Quantidade tratada: aguardando retorno do servidor.'),
            collection_process_step('Consultar feeds e APIs de noticias configuradas', 'Coleta noticias do dia usadas como insumo de contexto editorial.', 'Quantidade tratada: noticias encontradas.'),
            collection_process_step('Consultar fontes de tendencias configuradas', 'Busca sinais de tendencia nas fontes habilitadas.', 'Quantidade tratada: tendencias encontradas.'),
            collection_process_step('Derivar tendencias a partir das noticias quando necessario', 'Gera sinais auxiliares quando uma fonte externa nao retorna itens suficientes.', 'Quantidade tratada: tendencias derivadas.'),
            collection_process_step('Extrair e normalizar palavras-chave', 'Higieniza termos, temas e topicos para uso na priorizacao.', 'Quantidade tratada: termos extraidos.'),
            collection_process_step('Verificar duplicidades na base higienizada', 'Evita repetir noticias, tendencias e topicos ja persistidos para a data.', 'Quantidade tratada: registros novos e ignorados.'),
            collection_process_step('Salvar noticias, tendencias e topicos operacionais', 'Persiste os contextos coletados e a base operacional de topicos.', 'Quantidade tratada: contextos salvos.'),
            collection_process_step('Atualizar resumo operacional do contexto', 'Recalcula os contadores de noticias, tendencias e topicos.', 'Quantidade tratada: totais consolidados.'),
            collection_process_step('Finalizar execucao', 'Fecha o fluxo e devolve o resumo da execucao para a interface.', 'Quantidade tratada: resumo final.'),
        ],
        'process_priority' => [
            collection_process_step('Preparar execucao para a data selecionada', 'Valida a data e prepara os criterios ativos de priorizacao.', 'Quantidade tratada: aguardando retorno do servidor.'),
            collection_process_step('Carregar eventos historicos coletados', 'Seleciona todos os eventos historicos disponiveis para o dia avaliado.', 'Quantidade tratada: eventos avaliaveis.'),
            collection_process_step('Carregar noticias, tendencias e topicos higienizados', 'Reune os sinais de contexto que podem influenciar a justificativa editorial.', 'Quantidade tratada: contextos considerados.'),
            collection_process_step('Aplicar relevancia historica base', 'Calcula a forca inicial do evento antes das conexoes com o dia.', 'Quantidade tratada: eventos pontuados.'),
            collection_process_step('Calcular conexoes com noticias e tendencias', 'Compara termos, temas e entidades para explicar por que o fato importa hoje.', 'Quantidade tratada: conexoes encontradas.'),
            collection_process_step('Aplicar bonus, categoria e diversidade', 'Aplica aniversario, categoria e ajustes para evitar repeticao excessiva.', 'Quantidade tratada: ajustes aplicados.'),
            collection_process_step('Salvar score, motivos e resumo contextual', 'Grava o ranking, a decomposicao do score e a justificativa editorial.', 'Quantidade tratada: rankings salvos.'),
            collection_process_step('Atualizar status da priorizacao', 'Atualiza os indicadores da tabela de status dos processamentos.', 'Quantidade tratada: total consolidado para a data.'),
            collection_process_step('Finalizar execucao', 'Fecha o fluxo e devolve o resumo da execucao para a interface.', 'Quantidade tratada: resumo final.'),
        ],
        'default' => [
            collection_process_step('Preparar execucao', 'Valida parametros e inicia o fluxo.', 'Quantidade tratada: aguardando retorno.'),
            collection_process_step('Executar processamento', 'Executa a operacao solicitada.', 'Quantidade tratada: registros processados.'),
            collection_process_step('Atualizar resumo operacional', 'Recalcula os indicadores da tela.', 'Quantidade tratada: totais consolidados.'),
            collection_process_step('Finalizar execucao', 'Devolve o resumo final para a interface.', 'Quantidade tratada: resumo final.'),
        ],
    ];
}

function collection_status_rows(
    string $date,
    array $eventCounts,
    array $historicalSummary,
    array $importSummary,
    int $newsCount,
    int $trendCount,
    int $topicsCount,
    int $rankingCount
): array {
    return [
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
}

function collection_status_rows_html(array $collectionRows): string
{
    ob_start();
    foreach ($collectionRows as $row): ?>
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
