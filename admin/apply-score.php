<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$runDate = $_GET['date'] ?? $today['date'];
if (!is_string($runDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $runDate)) {
    $runDate = $today['date'];
}

render_page_start('Priorização centralizada', 'collections', 'admin', 'A execução da priorização foi movida para Coletas.');
?>
    <section class="panel">
        <h1>Processamento centralizado em Coletas</h1>
        <p>A priorização de eventos agora é executada na tela Coletas, junto com o acompanhamento dos processamentos por data.</p>
        <p><a class="button" href="/admin/collections.php?date=<?= h($runDate) ?>">Abrir Coletas</a></p>
    </section>
<?php render_page_end(); ?>
