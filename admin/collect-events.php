<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$error = null;
$imported = null;
$today = today_key();
$counts = events_count_by_review_status($today['month'], $today['day']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $imported = import_historical_events_for_today();
        $counts = events_count_by_review_status($today['month'], $today['day']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

render_page_start('Coletar eventos historicos', 'collect-events', 'admin', 'Busca fatos historicos do dia na Wikimedia e grava novos itens como nao avaliados.');
?>
    <section class="panel">
        <h1>Coleta de eventos historicos</h1>
        <form method="post">
            <button type="submit">Coletar eventos de hoje</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <?php if ($imported !== null): ?><p><?= h((string) $imported) ?> novos eventos importados.</p><?php endif; ?>
        <p>
            Hoje: <?= h((string) $counts['pending']) ?> nao avaliados,
            <?= h((string) $counts['approved']) ?> aprovados,
            <?= h((string) $counts['rejected']) ?> reprovados.
        </p>
        <p><a href="/admin/events.php?status=pending&month=<?= h((string) $today['month']) ?>&day=<?= h((string) $today['day']) ?>">Revisar eventos pendentes de hoje</a></p>
    </section>
<?php render_page_end(); ?>
