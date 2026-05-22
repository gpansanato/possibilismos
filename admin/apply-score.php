<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$result = null;
$error = null;
$today = today_key();
$approvedEvents = events_count_for_day($today['month'], $today['day']);
$topicsCount = current_topics_count_for_date($today['date']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = apply_daily_priority_score($today['date']);
        $approvedEvents = events_count_for_day($today['month'], $today['day']);
        $topicsCount = current_topics_count_for_date($today['date']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

render_page_start('Aplicar score de prioridade', 'apply-score', 'admin', 'Calcula prioridade dos eventos aprovados usando noticias, relevancia historica e criterios editoriais.');
?>
    <section class="panel">
        <h1>Score de avaliacao de prioridade</h1>
        <form method="post">
            <button type="submit">Aplicar score agora</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Eventos aprovados para hoje: <?= h((string) $approvedEvents) ?></p>
        <p>Topicos/noticias disponiveis: <?= h((string) $topicsCount) ?></p>
        <?php if ($result !== null): ?><p><?= count($result) ?> eventos ranqueados.</p><?php endif; ?>
    </section>

    <?php if ($result): ?>
        <section class="list">
            <?php foreach ($result as $item): ?>
                <article class="event">
                    <div class="year"><?= h((string) $item['event']['year']) ?></div>
                    <div>
                        <h2><?= h($item['event']['title']) ?></h2>
                        <p><?= h($item['context_summary']) ?></p>
                        <p class="meta">Score <?= h(number_format((float) $item['score'], 1)) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php render_page_end(); ?>
