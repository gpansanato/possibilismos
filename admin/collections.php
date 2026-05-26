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
            $result = collect_historical_events_for_day($dateParts['month'], $dateParts['day'], $actionDate);
            $failedStep = 'Atualizando resumo da coleta';
            $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
            $imports = event_import_summary_for_date($actionDate);
            $processTitle = 'Processamento 1: eventos historicos';
            $processDescription = 'Coleta, normalizacao, deduplicacao e enriquecimento dos fatos historicos da data selecionada.';
            $processSteps = [
                collection_process_step('Preparar execucao para a data selecionada', 'Parametros de data validados e fluxo iniciado.', '1 data processada'),
                collection_process_step('Executar matriz de coletores historicos', 'Wikidata roda como fonte principal e Wikimedia On This Day roda sempre em pt, en e es quando configurado.', ($result['found'] ?? $result['imported']) . ' candidatos encontrados'),
                collection_process_step('Normalizar titulo, data, ano, origem e chave canonica', 'Registros tratados para preservar origem, data, ano e chave canonica.', $imports['linked'] . ' imports vinculados'),
                collection_process_step('Verificar duplicidades nos eventos e imports', 'Comparacao aplicada antes de gravar novos eventos.', $imports['ignored'] . ' registros ignorados por duplicidade'),
                collection_process_step('Salvar ou atualizar eventos historicos coletados', 'Eventos canonicos persistidos ou vinculados a imports existentes.', $summary['total'] . ' eventos historicos na base para o dia'),
                collection_process_step('Executar enriquecimento leve integrado', 'Resumo Wikipedia, Commons e Wikidata complementar podem ser incorporados durante a coleta. Enriquecimentos documental, visual, geografico e academico permanecem separados.', $summary['enriched'] . ' eventos enriquecidos'),
                collection_process_step('Atualizar resumo operacional da coleta', 'Contadores recalculados para a tabela de status.', $summary['enrichment_records'] . ' enriquecimentos salvos'),
                collection_process_step('Finalizar execucao', 'Resumo final devolvido para a interface.', $imports['errors'] . ' falhas registradas'),
            ];
            foreach (array_slice($result['collectors'] ?? [], 0, 12) as $collector) {
                $processSteps[] = collection_process_step(
                    $collector['source'] . ' / ' . $collector['source_variant'],
                    'Coletor executado dentro da matriz de eventos historicos.',
                    $collector['found'] . ' encontrados; ' . $collector['imported'] . ' importados; ' . $collector['enriched'] . ' enriquecimentos; ' . $collector['failures'] . ' falhas; ' . number_format((float) $collector['duration'], 1, ',', '.') . 's'
                );
            }
            $processSummary = collection_process_summary($startedAt, $startedLabel, [
                'Encontrados' => $result['found'] ?? $result['imported'],
                'Importados' => $result['imported'],
                'Novos' => $imports['linked'],
                'Atualizados' => $imports['linked'],
                'Ignorados por duplicidade' => $imports['ignored'],
                'Enriquecidos' => $result['enriched'] ?? $summary['enriched'],
                'Falhas' => ($result['failures'] ?? 0) + $imports['errors'],
            ]);
            $message = 'Processamento de eventos historicos concluido.';
        } elseif ($action === 'process_context') {
            $failedStep = 'Consultando fonte de dados';
            $news = collect_daily_news_topics($actionDate);
            $failedStep = 'Recebendo registros';
            $trends = collect_daily_trend_topics($actionDate);
            $failedStep = 'Atualizando resumo da coleta';
            $topics = current_topics_count_for_date($actionDate);
            $processTitle = 'Processamento 2: contexto do dia';
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
            $processTitle = 'Processamento 3: priorizacao de eventos';
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
                <p>Defina a data de referencia e execute uma das tres etapas operacionais. A configuracao das fontes fica separada na tela Fontes.</p>
            </div>
        </div>
        <form class="date-filter" method="get">
            <label>Data de referencia <input type="date" name="date" id="collection-date-input" value="<?= h($date) ?>"></label>
            <button type="submit">Ver status</button>
            <a class="button button-secondary" href="/admin/collections.php">Hoje</a>
        </form>
        <form class="actions" method="post" id="collection-process-form" action="/admin/collections.php">
            <input type="hidden" name="date" id="collection-process-date" value="<?= h($date) ?>">
            <button name="action" value="process_events" type="submit" data-process-label="Processamento 1: eventos historicos">Processar eventos historicos</button>
            <button class="button-secondary" name="action" value="process_context" type="submit" data-process-label="Processamento 2: contexto do dia">Processar contexto do dia</button>
            <button class="button-secondary" name="action" value="process_priority" type="submit" data-process-label="Processamento 3: priorizacao de eventos">Aplicar priorizacao</button>
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
        if (!form || !panel || !logEl || !progressBar) {
            return;
        }

        const flowStepsByAction = <?= json_encode($collectionFlowSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let currentFlowSteps = [];
        let timer = null;
        let elapsedTimer = null;
        let startedAt = 0;
        let currentStep = 0;

        function setButtons(disabled) {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = disabled;
                button.classList.toggle('is-loading', disabled);
            });
        }

        function renderLog(activeIndex, statuses) {
            const steps = (currentFlowSteps.length ? currentFlowSteps : [{ title: 'Preparando execucao' }]).map(normalizeStep);
            logEl.innerHTML = steps.map((step, index) => {
                const status = statuses[index] || (index < activeIndex ? 'done' : index === activeIndex ? 'running' : 'pending');
                const statusLabel = status === 'done' ? 'Concluido' : status === 'running' ? 'Em execucao' : status === 'error' ? 'Erro' : 'Pendente';
                const detail = [step.description, step.result].filter(Boolean).join(' ');
                return '<div class="process-log__item is-' + status + '">' +
                    '<span>' + statusLabel + ':</span> ' +
                    '<strong>' + escapeHtml(step.title) + '</strong>' +
                    (detail ? '<p>' + escapeHtml(detail) + '</p>' : '') +
                    '</div>';
            }).join('');
            const done = statuses.filter((status) => status === 'done').length;
            const progress = Math.max(done, activeIndex + 1) / steps.length * 100;
            progressBar.style.width = Math.min(96, progress) + '%';
        }

        function startVisualProgress(label, action) {
            currentStep = 0;
            startedAt = Date.now();
            currentFlowSteps = flowStepsByAction[action] || flowStepsByAction.default || ['Preparando execucao', 'Finalizando execucao'];
            title.textContent = label || 'Processamento em andamento';
            description.textContent = 'A execucao foi iniciada. O servidor esta processando a solicitacao; mantenha esta tela aberta ate o resumo final aparecer.';
            summaryEl.innerHTML = '';
            updateElapsed();
            progressStatus.textContent = 'Processamento em andamento';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            renderLog(0, []);
            elapsedTimer = window.setInterval(updateElapsed, 1000);
            timer = window.setInterval(() => {
                currentStep = Math.min(currentStep + 1, currentFlowSteps.length - 1);
                renderLog(currentStep, []);
            }, 6500);
        }

        function finishVisualProgress(payload) {
            clearTimers();
            const steps = payload.steps && payload.steps.length ? payload.steps : currentFlowSteps;
            currentFlowSteps = steps.length ? steps : ['Finalizando execucao'];
            const statuses = currentFlowSteps.map(() => payload.ok ? 'done' : 'pending');
            if (!payload.ok) {
                const errorIndex = Math.max(0, Math.min(currentStep, currentFlowSteps.length - 1));
                for (let i = 0; i < errorIndex; i++) {
                    statuses[i] = 'done';
                }
                statuses[errorIndex] = 'error';
            }
            title.textContent = payload.title || (payload.ok ? 'Processamento concluido' : 'Processamento interrompido');
            description.textContent = payload.ok
                ? (payload.description || payload.message || 'Execucao finalizada.')
                : (payload.error || 'Nao foi possivel concluir o processamento.');
            progressStatus.textContent = payload.ok ? 'Processamento concluido' : 'Processamento interrompido';
            updateElapsed(payload.summary && payload.summary.Duracao ? payload.summary.Duracao : null);
            renderLog(currentFlowSteps.length, statuses);
            progressBar.style.width = payload.ok ? '100%' : Math.max(12, currentStep / currentFlowSteps.length * 100) + '%';
            progressBar.classList.toggle('is-error', !payload.ok);
            renderSummary(payload.summary || {});
            if (payload.tableRows && statusRows) {
                statusRows.innerHTML = payload.tableRows;
            }
            if (payload.date && statusDate) {
                statusDate.textContent = payload.date;
            }
        }

        function clearTimers() {
            window.clearInterval(timer);
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
            startVisualProgress(submitter.dataset.processLabel, submitter.value);

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

function collection_action_label(string $action): string
{
    return [
        'process_events' => 'Processamento 1: eventos historicos',
        'process_context' => 'Processamento 2: contexto do dia',
        'process_priority' => 'Processamento 3: priorizacao de eventos',
    ][$action] ?? 'Processamento operacional';
}

function collection_flow_steps(): array
{
    return [
        'process_events' => [
            collection_process_step('Preparar execucao para a data selecionada', 'Valida a data, monta parametros da coleta e registra o inicio do fluxo.', 'Quantidade tratada: aguardando retorno do servidor.'),
            collection_process_step('Consultar Wikidata para eventos historicos do dia', 'Busca fatos historicos associados ao dia e mes selecionados.', 'Quantidade tratada: eventos encontrados na fonte.'),
            collection_process_step('Executar Wikimedia On This Day', 'Coleta efemerides em pt, en e es como fonte paralela, nao apenas fallback.', 'Quantidade tratada: candidatos Wikimedia consultados.'),
            collection_process_step('Normalizar titulo, data, ano, origem e chave canonica', 'Remove duplicidade textual, separa ano/data e prepara a chave canonica do evento.', 'Quantidade tratada: registros importados normalizados.'),
            collection_process_step('Verificar duplicidades nos eventos e imports', 'Compara fonte, chave canonica, titulo, ano e data historica antes de gravar.', 'Quantidade tratada: novos, vinculados e ignorados por duplicidade.'),
            collection_process_step('Salvar ou atualizar eventos historicos coletados', 'Persiste o evento canonico ou atualiza o vinculo de importacao existente.', 'Quantidade tratada: eventos salvos ou atualizados.'),
            collection_process_step('Executar enriquecimento leve integrado', 'Aplica apenas enriquecimento leve durante a coleta principal; demais enriquecimentos ficam separados.', 'Quantidade tratada: enriquecimentos leves gravados.'),
            collection_process_step('Atualizar resumo operacional da coleta', 'Recalcula contadores exibidos na tabela de status dos processamentos.', 'Quantidade tratada: total consolidado para a data.'),
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
            'name' => 'Coleta, normalizacao e enriquecimento dos eventos historicos',
            'status' => $historicalSummary['total'] > 0 ? 'Concluida' : 'Pendente',
            'count' => $historicalSummary['total'],
            'detail' => $eventCounts['pending'] . ' nao publicados, ' . $eventCounts['approved'] . ' publicados, ' . $eventCounts['rejected'] . ' reprovados; ' . $importSummary['linked'] . ' imports vinculados; ' . $historicalSummary['enrichment_records'] . ' enriquecimentos',
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
