<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$rankings = rankings_for_date($today['date']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
<main class="page">
    <nav class="nav">
        <a href="/admin/dashboard.php">Hoje</a>
        <a href="/admin/events.php">Eventos</a>
        <a href="/admin/run.php">Rodar selecao</a>
        <a href="/admin/logout.php">Sair</a>
    </nav>

    <h1>Sugestoes de hoje</h1>
    <?php if (!$rankings): ?>
        <section class="empty">
            <p>Nenhuma sugestao gerada. Rode a selecao diaria.</p>
        </section>
    <?php endif; ?>

    <section class="list">
        <?php foreach ($rankings as $item): ?>
            <article class="event">
                <div class="year"><?= h($item['year']) ?></div>
                <div>
                    <h2><?= h($item['title']) ?></h2>
                    <p><?= h($item['description']) ?></p>
                    <p class="context"><?= h($item['context_summary']) ?></p>
                    <p class="meta">Status: <?= h($item['status']) ?> | Score: <?= h(number_format((float) $item['score'], 1)) ?></p>
                    <form method="post" action="/admin/update-ranking.php">
                        <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                        <button name="status" value="approved" type="submit">Aprovar</button>
                        <button class="danger" name="status" value="rejected" type="submit">Rejeitar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
</main>
</body>
</html>
