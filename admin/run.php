<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$result = null;
$error = null;
$today = today_key();
$eventsBefore = events_count_for_day($today['month'], $today['day']);
$eventsAfter = $eventsBefore;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = run_daily_ranking();
        $eventsAfter = events_count_for_day($today['month'], $today['day']);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rodar selecao</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<main class="page">
    <nav class="nav">
        <a href="/admin/dashboard.php">Hoje</a>
        <a href="/admin/events.php">Eventos</a>
        <a href="/admin/logout.php">Sair</a>
    </nav>

    <section class="panel">
        <h1>Rodar selecao diaria</h1>
        <form method="post">
            <button type="submit">Executar agora</button>
        </form>
        <?php if ($error): ?><p><?= h($error) ?></p><?php endif; ?>
        <p>Eventos cadastrados para hoje: <?= h((string) $eventsAfter) ?></p>
        <?php if ($result !== null): ?>
            <p><?= count($result) ?> eventos ranqueados. Antes da coleta havia <?= h((string) $eventsBefore) ?> eventos.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
