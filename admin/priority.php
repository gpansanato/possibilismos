<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$runDate = $_GET['date'] ?? $today['date'];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    update_scoring_settings($_POST['settings'] ?? []);
    $saved = true;
}

$settings = scoring_settings();
$definitions = scoring_setting_definitions();
$rankings = rankings_for_date($runDate);
$topics = topics_for_date($runDate);
$newsCount = current_topics_count_for_date_and_source($runDate, 'rss');
$trendsCount = current_topics_count_for_date_and_source($runDate, 'trend');
$currentYear = (int) substr($runDate, 0, 4);

render_page_start('Prioridade dos fatos', 'priority', 'admin', 'Lista os fatos relevantes obtidos e detalha como o score de prioridade e calculado.');
?>
    <section class="panel">
        <h1>Parametros do score</h1>
        <?php if ($saved): ?><p>Parametros atualizados.</p><?php endif; ?>
        <form class="settings-grid" method="post">
            <?php foreach ($definitions as $key => $definition): ?>
                <label>
                    <?= h($definition['label']) ?>
                    <input type="number" step="0.01" name="settings[<?= h($key) ?>]" value="<?= h((string) $settings[$key]) ?>">
                </label>
            <?php endforeach; ?>
            <button type="submit">Salvar parametros</button>
        </form>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow">
                <?= h($runDate) ?> |
                <?= count($rankings) ?> fatos ranqueados |
                <?= h((string) $newsCount) ?> noticias |
                <?= h((string) $trendsCount) ?> tendencias
            </p>
            <h2>Fatos relevantes obtidos</h2>
        </div>
        <form class="inline-filter" method="get">
            <label>Data <input type="date" name="date" value="<?= h($runDate) ?>"></label>
            <button type="submit">Ver</button>
        </form>
    </section>

    <?php if (!$rankings): ?>
        <section class="empty">
            <p>Nenhum score aplicado para esta data. Use a etapa Aplicar score.</p>
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
                        <span>Score salvo <?= h(number_format((float) $item['score'], 1)) ?></span>
                        <span>Publicacao <?= h($item['status']) ?></span>
                        <span>Evento <?= h(event_review_status_label($item['review_status'])) ?></span>
                    </div>
                    <div class="score-grid">
                        <span>Historico: <?= h(number_format($components['historical'], 1)) ?></span>
                        <span>Noticias: <?= h(number_format($components['news'], 1)) ?></span>
                        <span>Tendencias: <?= h(number_format($components['trends'], 1)) ?></span>
                        <span>Aniversario: <?= h(number_format($components['anniversary'], 1)) ?></span>
                        <span>Categoria: <?= h(number_format($components['category'], 1)) ?></span>
                        <span>Diversidade: <?= h(number_format($components['diversity'], 1)) ?></span>
                    </div>
                    <p class="context"><?= h($item['context_summary']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php render_page_end(); ?>
