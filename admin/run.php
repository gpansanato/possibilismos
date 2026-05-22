<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$result = null;
$error = null;
$today = today_key();
$eventsBefore = events_count_for_day($today['month'], $today['day']);
$eventsAfter = $eventsBefore;
$topicsAfter = current_topics_count_for_date($today['date']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = run_daily_ranking();
        $eventsAfter = events_count_for_day($today['month'], $today['day']);
        $topicsAfter = current_topics_count_for_date($today['date']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
render_page_start('Rodar selecao diaria', 'run', 'admin', 'Executa coleta da internet, ranking e gravacao das sugestoes do dia.');
?>
    <section class="panel">
        <h1>Rodar selecao diaria</h1>
        <form method="post">
            <button type="submit">Executar agora</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Eventos aprovados para hoje: <?= h((string) $eventsAfter) ?></p>
        <p>Topicos/noticias coletados para hoje: <?= h((string) $topicsAfter) ?></p>
        <?php if ($result !== null): ?>
            <p><?= count($result) ?> eventos ranqueados. Antes da coleta havia <?= h((string) $eventsBefore) ?> eventos aprovados.</p>
        <?php endif; ?>
    </section>
<?php render_page_end(); ?>
