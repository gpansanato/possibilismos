<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$error = null;
$topics = null;
$today = today_key();
$topicsCount = current_topics_count_for_date_and_source($today['date'], 'trend');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $topics = collect_daily_trend_topics($today['date']);
        $topicsCount = count($topics);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

render_page_start('Coletar tendencias', 'collect-trends', 'admin', 'Busca tendencias do dia e salva os temas para orientar a priorizacao dos eventos historicos.');
?>
    <section class="panel">
        <h1>Coleta de tendencias do dia</h1>
        <form method="post">
            <button type="submit">Coletar tendencias de hoje</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Tendencias registradas para hoje: <?= h((string) $topicsCount) ?></p>
    </section>

    <?php if ($topics): ?>
        <section class="list">
            <?php foreach (array_slice($topics, 0, 10) as $topic): ?>
                <article class="event">
                    <div class="year">Trend</div>
                    <div>
                        <h2><?= h($topic['title']) ?></h2>
                        <p class="meta"><?= h($topic['source']) ?></p>
                        <p><?= h($topic['keywords']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php render_page_end(); ?>
