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
                collection_process_step('Coleta de eventos historicos', 'Fontes estruturais consultadas para identificar fatos associados ao dia.', $result['imported'] . ' eventos processados'),
                collection_process_step('Normalizacao e vinculo canonico', 'Registros importados foram tratados para evitar duplicidade e preservar origem, data, ano e chave canonica.', $imports['linked'] . ' imports vinculados'),
                collection_process_step('Enriquecimento integrado', 'Fontes de apoio adicionam resumo, imagem, documentos e materiais complementares quando disponiveis.', $summary['enriched'] . ' eventos enriquecidos; ' . $summary['enrichment_records'] . ' registros salvos'),
            ];
            $processSummary = collection_process_summary($startedAt, $startedLabel, [
                'Registros encontrados' => $result['imported'],
                'Novos ou atualizados' => $imports['linked'],
                'Ignorados por duplicidade' => $imports['ignored'],
                'Falhas' => $imports['errors'],
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
                collection_process_step('Coleta de noticias', 'Feeds e fontes noticiosas configuradas foram lidos para persistir itens de contexto editorial.', count($news) . ' noticias persistidas'),
                collection_process_step('Coleta de tendencias', 'Sinais de tendencia foram coletados ou derivados das noticias quando a fonte externa nao retornou itens.', count($trends) . ' tendencias persistidas'),
                collection_process_step('Base higienizada de contexto', 'Itens coletados foram reconstruidos na base operacional usada pela priorizacao.', $topics . ' topicos disponiveis'),
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
                collection_process_step('Leitura dos eventos historicos', 'Todos os eventos coletados para o dia foram considerados antes da ordenacao editorial.', $summary['total'] . ' eventos avaliaveis'),
                collection_process_step('Carregamento do contexto do dia', 'Noticias, tendencias e topicos higienizados foram reunidos como sinais de apoio.', $contextTotal . ' contextos; ' . $topics . ' topicos'),
                collection_process_step('Aplicacao dos criterios de priorizacao', 'O sistema recalculou score, motivos e resumo contextual para apoiar a curadoria.', count($ranked) . ' rankings gerados'),
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
            <label>Data de referencia <input type="date" name="date" value="<?= h($date) ?>"></label>
            <button type="submit">Ver status</button>
            <a class="button button-secondary" href="/admin/collections.php">Hoje</a>
        </form>
        <form class="actions" method="post" id="collection-process-form">
            <input type="hidden" name="date" value="<?= h($date) ?>">
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
        <div class="process-log" id="collection-progress-log">
            <?php if ($processSteps): ?>
                <?php foreach ($processSteps as $index => $step): ?>
                    <div class="process-log__item is-done">
                        <span>Concluido</span>
                        <strong><?= h($step['title']) ?></strong>
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
                <p class="eyebrow"><?= h($date) ?></p>
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
        const statusRows = document.getElementById('collection-status-rows');
        if (!form || !panel || !logEl || !progressBar) {
            return;
        }

        const flowStepsByAction = <?= json_encode($collectionFlowSteps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let currentFlowSteps = [];
        let timer = null;
        let currentStep = 0;

        function setButtons(disabled) {
            form.querySelectorAll('button[type="submit"]').forEach((button) => {
                button.disabled = disabled;
                button.classList.toggle('is-loading', disabled);
            });
        }

        function renderLog(activeIndex, statuses) {
            const steps = currentFlowSteps.length ? currentFlowSteps : ['Preparando execucao'];
            logEl.innerHTML = steps.map((label, index) => {
                const status = statuses[index] || (index < activeIndex ? 'done' : index === activeIndex ? 'running' : 'pending');
                const statusLabel = status === 'done' ? 'Concluido' : status === 'running' ? 'Em execucao' : status === 'error' ? 'Erro' : 'Pendente';
                return '<div class="process-log__item is-' + status + '">' +
                    '<span>' + statusLabel + '</span>' +
                    '<strong>' + escapeHtml(label) + '</strong>' +
                    '</div>';
            }).join('');
            const done = statuses.filter((status) => status === 'done').length;
            const progress = Math.max(done, activeIndex + 1) / steps.length * 100;
            progressBar.style.width = Math.min(100, progress) + '%';
        }

        function startVisualProgress(label, action) {
            currentStep = 0;
            currentFlowSteps = flowStepsByAction[action] || flowStepsByAction.default || ['Preparando execucao', 'Finalizando execucao'];
            title.textContent = label || 'Processamento em andamento';
            description.textContent = 'A execucao foi iniciada. Acompanhe as etapas enquanto o servidor processa a coleta.';
            summaryEl.innerHTML = '';
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            renderLog(0, []);
            timer = window.setInterval(() => {
                currentStep = Math.min(currentStep + 1, currentFlowSteps.length - 2);
                renderLog(currentStep, []);
            }, 6500);
        }

        function finishVisualProgress(payload) {
            window.clearInterval(timer);
            const steps = currentFlowSteps.length ? currentFlowSteps : (payload.steps || []).map((step) => step.title);
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
            renderLog(currentFlowSteps.length, statuses);
            progressBar.style.width = payload.ok ? '100%' : Math.max(12, currentStep / currentFlowSteps.length * 100) + '%';
            progressBar.classList.toggle('is-error', !payload.ok);
            renderSummary(payload.summary || {});
            if (payload.tableRows && statusRows) {
                statusRows.innerHTML = payload.tableRows;
            }
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
            formData.set(submitter.name, submitter.value);
            formData.set('async', '1');

            try {
                const response = await fetch(form.action || window.location.href, {
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
            'Preparar execucao para a data selecionada',
            'Consultar Wikidata para eventos historicos do dia',
            'Acionar apoio Wikimedia quando necessario',
            'Normalizar titulo, data, ano, origem e chave canonica',
            'Verificar duplicidades nos eventos e imports',
            'Salvar ou atualizar eventos historicos coletados',
            'Executar enriquecimento integrado nas fontes ativas',
            'Atualizar resumo operacional da coleta',
            'Finalizar execucao',
        ],
        'process_context' => [
            'Preparar execucao para a data selecionada',
            'Consultar feeds e APIs de noticias configuradas',
            'Consultar fontes de tendencias configuradas',
            'Derivar tendencias a partir das noticias quando necessario',
            'Extrair e normalizar palavras-chave',
            'Verificar duplicidades na base higienizada',
            'Salvar noticias, tendencias e topicos operacionais',
            'Atualizar resumo operacional do contexto',
            'Finalizar execucao',
        ],
        'process_priority' => [
            'Preparar execucao para a data selecionada',
            'Carregar eventos historicos coletados',
            'Carregar noticias, tendencias e topicos higienizados',
            'Aplicar relevancia historica base',
            'Calcular conexoes com noticias e tendencias',
            'Aplicar bonus, categoria e diversidade',
            'Salvar score, motivos e resumo contextual',
            'Atualizar status da priorizacao',
            'Finalizar execucao',
        ],
        'default' => [
            'Preparar execucao',
            'Executar processamento',
            'Atualizar resumo operacional',
            'Finalizar execucao',
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
