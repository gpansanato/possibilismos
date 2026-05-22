<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$error = null;
$topics = null;
$today = today_key();
$topicsCount = current_topics_count_for_date_and_source($today['date'], 'rss');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $topics = collect_daily_news_topics($today['date']);
        $topicsCount = count($topics);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

render_page_start('Coletar noticias', 'collect-news', 'admin', 'Busca noticias por RSS e extrai topicos para orientar a priorizacao.');
?>
    <section class="panel">
        <h1>Coleta de noticias e topicos</h1>
        <form method="post">
            <button type="submit">Coletar noticias de hoje</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Topicos/noticias registrados para hoje: <?= h((string) $topicsCount) ?></p>
        <p>Noticias persistidas na base higienizada: <?= h((string) collected_contexts_count_for_date($today['date'], 'news')) ?></p>
    </section>

    <?php if ($topics): ?>
        <section class="list">
            <?php foreach (array_slice($topics, 0, 10) as $topic): ?>
                <article class="event">
                    <div class="year">RSS</div>
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
