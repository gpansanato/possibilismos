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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $actionDate = $_POST['date'] ?? $date;
    if (!is_string($actionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
        $actionDate = $today['date'];
    }
    $date = $actionDate;
    $dateParts = date_parts_from_run_date($actionDate);

    try {
        if ($action === 'process_events') {
            $result = collect_historical_events_for_day($dateParts['month'], $dateParts['day'], $actionDate);
            $summary = historical_collection_summary_for_day($dateParts['month'], $dateParts['day']);
            $imports = event_import_summary_for_date($actionDate);
            $processTitle = 'Processamento 1: eventos historicos';
            $processDescription = 'Coleta, normalizacao, deduplicacao e enriquecimento dos fatos historicos da data selecionada.';
            $processSteps = [
                collection_process_step('Coleta de eventos historicos', 'Fontes estruturais consultadas para identificar fatos associados ao dia.', $result['imported'] . ' eventos processados'),
                collection_process_step('Normalizacao e vinculo canonico', 'Registros importados foram tratados para evitar duplicidade e preservar origem, data, ano e chave canonica.', $imports['linked'] . ' imports vinculados'),
                collection_process_step('Enriquecimento integrado', 'Fontes de apoio adicionam resumo, imagem, documentos e materiais complementares quando disponiveis.', $summary['enriched'] . ' eventos enriquecidos; ' . $summary['enrichment_records'] . ' registros salvos'),
            ];
            $message = 'Processamento de eventos historicos concluido.';
        } elseif ($action === 'process_context') {
            $news = collect_daily_news_topics($actionDate);
            $trends = collect_daily_trend_topics($actionDate);
            $topics = current_topics_count_for_date($actionDate);
            $processTitle = 'Processamento 2: contexto do dia';
            $processDescription = 'Coleta e higienizacao integrada de noticias e tendencias usadas como insumos contextuais.';
            $processSteps = [
                collection_process_step('Coleta de noticias', 'Feeds e fontes noticiosas configuradas foram lidos para persistir itens de contexto editorial.', count($news) . ' noticias persistidas'),
                collection_process_step('Coleta de tendencias', 'Sinais de tendencia foram coletados ou derivados das noticias quando a fonte externa nao retornou itens.', count($trends) . ' tendencias persistidas'),
                collection_process_step('Base higienizada de contexto', 'Itens coletados foram reconstruidos na base operacional usada pela priorizacao.', $topics . ' topicos disponiveis'),
            ];
            $message = 'Processamento de contexto concluido.';
        } elseif ($action === 'process_priority') {
            $ranked = apply_daily_priority_score($actionDate);
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
            $message = count($ranked) . ' eventos priorizados para ' . $actionDate . '.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
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

$collectionRows = [
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

render_page_start('Coletas', 'collections', 'admin', 'Home operacional para executar e acompanhar os processamentos por data.');
?>
    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

    <section class="panel">
        <form class="date-filter" method="get">
            <label>Data de referencia <input type="date" name="date" value="<?= h($date) ?>"></label>
            <button type="submit">Ver status</button>
            <a class="button button-secondary" href="/admin/collections.php">Hoje</a>
        </form>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Processamentos</p>
                <h2>Executar fluxo operacional</h2>
                <p>Escolha uma das tres etapas para a data selecionada. A configuracao das fontes fica separada na tela Fontes.</p>
            </div>
        </div>
        <form class="actions" method="post">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <button name="action" value="process_events" type="submit">Processar eventos historicos</button>
            <button class="button-secondary" name="action" value="process_context" type="submit">Processar contexto do dia</button>
            <button class="button-secondary" name="action" value="process_priority" type="submit">Aplicar priorizacao</button>
        </form>
    </section>

    <section class="panel process-panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Acompanhamento</p>
                <h2><?= h($processTitle ?: 'Fluxo operacional por etapas') ?></h2>
                <p><?= h($processDescription ?: 'Selecione um processamento para acompanhar as etapas executadas para a data de referencia.') ?></p>
            </div>
        </div>
        <div class="process-steps">
            <?php if ($processSteps): ?>
                <?php foreach ($processSteps as $index => $step): ?>
                    <article class="process-step is-done">
                        <span class="process-step__index"><?= h((string) ($index + 1)) ?></span>
                        <div>
                            <h3><?= h($step['title']) ?></h3>
                            <p><?= h($step['description']) ?></p>
                            <strong><?= h($step['result']) ?></strong>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <article class="process-step">
                    <span class="process-step__index">1</span>
                    <div>
                        <h3>Eventos historicos</h3>
                        <p>Coleta, normalizacao e enriquecimento executados em um unico processamento.</p>
                    </div>
                </article>
                <article class="process-step">
                    <span class="process-step__index">2</span>
                    <div>
                        <h3>Noticias e tendencias</h3>
                        <p>Coleta integrada da base de contexto usada como insumo editorial.</p>
                    </div>
                </article>
                <article class="process-step">
                    <span class="process-step__index">3</span>
                    <div>
                        <h3>Priorizacao</h3>
                        <p>Aplicacao dos criterios configurados para gerar ranking e justificativas.</p>
                    </div>
                </article>
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
                <tbody>
                    <?php foreach ($collectionRows as $row): ?>
                        <tr>
                            <td data-label="Data"><?= h($row['date']) ?></td>
                            <td data-label="Processamento"><strong><?= h($row['name']) ?></strong></td>
                            <td data-label="Status"><span class="status-badge <?= $row['count'] > 0 ? 'is-approved' : 'is-pending' ?>"><?= h($row['status']) ?></span></td>
                            <td data-label="Registros"><?= h((string) $row['count']) ?></td>
                            <td data-label="Detalhe"><?= h($row['detail']) ?></td>
                            <td data-label="Acoes"><a class="button button-secondary is-compact" href="<?= h($row['href']) ?>">Abrir</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
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
