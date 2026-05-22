<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/collections.php');
}

$result = null;
$error = null;
$today = today_key();
$eventsBefore = events_count_for_day($today['month'], $today['day']);
$eventsAfter = $eventsBefore;
$topicsAfter = current_topics_count_for_date($today['date']);
$newsAfter = current_topics_count_for_date_and_source($today['date'], 'rss');
$trendsAfter = current_topics_count_for_date_and_source($today['date'], 'trend');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = run_daily_ranking();
        $eventsAfter = events_count_for_day($today['month'], $today['day']);
        $topicsAfter = current_topics_count_for_date($today['date']);
        $newsAfter = current_topics_count_for_date_and_source($today['date'], 'rss');
        $trendsAfter = current_topics_count_for_date_and_source($today['date'], 'trend');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
render_page_start('Execucao completa', 'run', 'admin', 'Executa em sequencia: coleta de eventos, coleta de noticias e aplicacao do score.');
?>
    <section class="panel">
        <h1>Executar processo completo</h1>
        <form method="post">
            <button type="submit">Executar todas as etapas</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Eventos aprovados para hoje: <?= h((string) $eventsAfter) ?></p>
        <p>Contextos coletados para hoje: <?= h((string) $topicsAfter) ?> no total, <?= h((string) $newsAfter) ?> noticias, <?= h((string) $trendsAfter) ?> tendencias.</p>
        <?php if ($result !== null): ?>
            <p><?= count($result) ?> eventos ranqueados. Antes da coleta havia <?= h((string) $eventsBefore) ?> eventos aprovados.</p>
        <?php endif; ?>
    </section>
<?php render_page_end(); ?>
