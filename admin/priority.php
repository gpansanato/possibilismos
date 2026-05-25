<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$runDate = $_GET['date'] ?? $today['date'];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedDate = $_POST['date'] ?? $runDate;
    if (is_string($postedDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $postedDate)) {
        $runDate = $postedDate;
    }

    update_scoring_settings($_POST['settings'] ?? []);
    $saved = true;
}

$settings = scoring_settings();
$definitions = scoring_setting_definitions();
$rankings = rankings_for_date($runDate);
$topics = topics_for_date($runDate);
$newsCount = collected_contexts_count_for_date($runDate, 'news');
$trendsCount = collected_contexts_count_for_date($runDate, 'trend');
$currentYear = (int) substr($runDate, 0, 4);

render_page_start('Priorização de eventos', 'priority', 'admin', 'Revise critérios, ajuste parâmetros e analise a priorização dos eventos históricos.');
?>
    <section class="panel">
        <h1>Critérios de priorização</h1>
        <div class="feature-grid feature-grid--three">
            <article class="feature-card"><h3>Relevância histórica</h3><p>Parte do score base do evento já curado.</p></article>
            <article class="feature-card"><h3>Contexto do dia</h3><p>Relaciona o evento com notícias e tendências higienizadas da data.</p></article>
            <article class="feature-card"><h3>Marco temporal</h3><p>Aplica bônus quando o aniversário histórico é editorialmente relevante.</p></article>
        </div>
    </section>

    <section class="panel">
        <h1>Parâmetros de priorização</h1>
        <p>Revise os parâmetros de cálculo. A execução da priorização foi centralizada em Fontes.</p>
        <?php if ($saved): ?><p>Parâmetros atualizados.</p><?php endif; ?>
        <form class="settings-grid" method="post">
            <label>
                Data avaliada
                <input type="date" name="date" value="<?= h($runDate) ?>">
            </label>
            <?php foreach ($definitions as $key => $definition): ?>
                <label>
                    <?= h($definition['label']) ?>
                    <input type="number" step="0.01" name="settings[<?= h($key) ?>]" value="<?= h((string) $settings[$key]) ?>">
                </label>
            <?php endforeach; ?>
            <button type="submit">Salvar parâmetros</button>
            <a class="button button-secondary" href="/admin/sources.php?date=<?= h($runDate) ?>">Executar em Fontes</a>
            <a class="button button-secondary" href="/admin/events.php?date=<?= h($runDate) ?>">Voltar para eventos</a>
        </form>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow">
                <?= h($runDate) ?> |
                <?= count($rankings) ?> fatos priorizados |
                <?= h((string) $newsCount) ?> notícias |
                <?= h((string) $trendsCount) ?> tendências
            </p>
            <h2>Eventos históricos priorizados</h2>
        </div>
        <form class="inline-filter" method="get">
            <label>Data <input type="date" name="date" value="<?= h($runDate) ?>"></label>
            <button type="submit">Ver</button>
        </form>
    </section>

    <?php if (!$rankings): ?>
        <section class="empty">
            <p>Nenhuma priorização aplicada para esta data. Execute a priorização em Fontes.</p>
        </section>
    <?php endif; ?>

    <section class="list">
        <?php $categoryCounts = []; ?>
        <?php foreach ($rankings as $item): ?>
            <?php
                $event = [
                    'title' => $item['title'],
                    'description' => $item['description'],
                    'category' => $item['category'],
                    'region' => $item['region'],
                    'year' => $item['year'],
                    'base_score' => $item['base_score'],
                ];
                $components = score_event_components($event, $topics, $currentYear, $settings);
                $category = (string) $item['category'];
                $components['diversity'] = -min(
                    (float) $settings['diversity_max'],
                    ($categoryCounts[$category] ?? 0) * (float) $settings['diversity_penalty']
                );
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            ?>
            <article class="event">
                <div class="year"><?= h((string) $item['year']) ?></div>
                <div>
                    <h2><?= h($item['title']) ?></h2>
                    <p><?= h($item['description']) ?></p>
                    <div class="meta">
                        <span>Prioridade salva <?= h(number_format((float) $item['score'], 1)) ?></span>
                        <span>Publicação <?= h($item['status']) ?></span>
                        <span>Evento <?= h(event_review_status_label($item['review_status'])) ?></span>
                    </div>
                    <div class="score-grid">
                        <span>Histórico: <?= h(number_format($components['historical'], 1)) ?></span>
                        <span>Notícias: <?= h(number_format($components['news'], 1)) ?></span>
                        <span>Tendências: <?= h(number_format($components['trends'], 1)) ?></span>
                        <span>Aniversário: <?= h(number_format($components['anniversary'], 1)) ?></span>
                        <span>Categoria: <?= h(number_format($components['category'], 1)) ?></span>
                        <span>Diversidade: <?= h(number_format($components['diversity'], 1)) ?></span>
                    </div>
                    <p class="context"><?= h($item['context_summary']) ?></p>
                    <div class="actions">
                        <a class="button button-secondary" href="/admin/event-detail.php?id=<?= h((string) $item['event_id']) ?>">Abrir dossiê editorial</a>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php render_page_end(); ?>
