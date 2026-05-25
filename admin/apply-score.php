<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$runDate = $_GET['date'] ?? $today['date'];
if (!is_string($runDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
    $runDate = $today['date'];
}

render_page_start('Priorização centralizada', 'sources', 'admin', 'A execução da priorização foi movida para Fontes e coletas.');
?>
    <section class="panel">
        <h1>Processamento centralizado em Fontes</h1>
        <p>A priorização de eventos agora é executada na central de Fontes, junto com coletas de eventos, notícias, tendências e enriquecimentos.</p>
        <p><a class="button" href="/admin/sources.php?date=<?= h($runDate) ?>">Abrir Fontes e coletas</a></p>
    </section>
<?php render_page_end(); ?>
