<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$result = null;
$error = null;
$today = today_key();
$runDate = $_POST['date'] ?? $_GET['date'] ?? $today['date'];
$dateParts = date_parts_from_run_date($runDate);
$historicalEvents = historical_events_count_for_day($dateParts['month'], $dateParts['day']);
$topicsCount = current_topics_count_for_date($runDate);
$newsCount = collected_contexts_count_for_date($runDate, 'news');
$trendsCount = collected_contexts_count_for_date($runDate, 'trend');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = apply_daily_priority_score($runDate);
        $historicalEvents = historical_events_count_for_day($dateParts['month'], $dateParts['day']);
        $topicsCount = current_topics_count_for_date($runDate);
        $newsCount = collected_contexts_count_for_date($runDate, 'news');
        $trendsCount = collected_contexts_count_for_date($runDate, 'trend');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

render_page_start('Priorização de eventos históricos', 'apply-score', 'admin', 'Relaciona todos os eventos históricos aprovados do dia avaliado com todas as notícias e tendências da base de contexto.');
?>
    <section class="panel">
        <h1>Executar priorizacao</h1>
        <form method="post">
            <label>Data avaliada <input type="date" name="date" value="<?= h($runDate) ?>"></label>
            <button type="submit">Priorizar eventos agora</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Eventos historicos no dia avaliado: <?= h((string) $historicalEvents) ?></p>
        <p>Contextos disponiveis na base higienizada: <?= h((string) ($newsCount + $trendsCount)) ?> no total, <?= h((string) $newsCount) ?> noticias, <?= h((string) $trendsCount) ?> tendencias.</p>
        <p>Topicos operacionais reconstruidos para o calculo: <?= h((string) $topicsCount) ?></p>
        <?php if ($result !== null): ?><p><?= count($result) ?> eventos priorizados e salvos.</p><?php endif; ?>
    </section>

    <?php if ($result): ?>
        <section class="list">
            <?php foreach ($result as $item): ?>
                <article class="event">
                    <div class="year"><?= h((string) $item['event']['year']) ?></div>
                    <div>
                        <h2><?= h($item['event']['title']) ?></h2>
                        <p><?= h($item['context_summary']) ?></p>
                        <p class="meta">Prioridade <?= h(number_format((float) $item['score'], 1)) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php render_page_end(); ?>
