<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare(
        'INSERT INTO events
         (event_month, event_day, year, title, description, category, region, source_url, base_score, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())'
    );
    $stmt->execute([
        (int) $_POST['event_month'],
        (int) $_POST['event_day'],
        (int) $_POST['year'],
        $_POST['title'],
        $_POST['description'],
        $_POST['category'],
        $_POST['region'],
        $_POST['source_url'] ?: null,
        (float) $_POST['base_score'],
    ]);

    redirect('/admin/events.php');
}

$events = db()->query('SELECT * FROM events ORDER BY event_month, event_day, year')->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eventos</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<main class="page">
    <nav class="nav">
        <a href="/admin/dashboard.php">Hoje</a>
        <a href="/admin/run.php">Rodar selecao</a>
        <a href="/admin/logout.php">Sair</a>
    </nav>

    <section class="panel">
        <h1>Novo evento historico</h1>
        <form class="form" method="post">
            <label>Mes <input type="number" name="event_month" min="1" max="12" required></label>
            <label>Dia <input type="number" name="event_day" min="1" max="31" required></label>
            <label>Ano <input type="number" name="year" required></label>
            <label>Titulo <input name="title" required></label>
            <label>Descricao <textarea name="description" required></textarea></label>
            <label>Categoria <input name="category" required></label>
            <label>Regiao <input name="region" required></label>
            <label>Fonte URL <input type="url" name="source_url"></label>
            <label>Relevancia base <input type="number" name="base_score" min="0" max="100" step="0.1" value="50" required></label>
            <button type="submit">Salvar</button>
        </form>
    </section>

    <h2>Eventos cadastrados</h2>
    <section class="list">
        <?php foreach ($events as $event): ?>
            <article class="event">
                <div class="year"><?= h($event['year']) ?></div>
                <div>
                    <h2><?= h($event['title']) ?></h2>
                    <p><?= h($event['description']) ?></p>
                    <p class="meta"><?= h($event['event_day']) ?>/<?= h($event['event_month']) ?> | <?= h($event['category']) ?> | <?= h($event['region']) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
