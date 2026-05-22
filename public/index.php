<?php
require_once __DIR__ . '/../app/bootstrap.php';

$today = today_key();
$items = published_rankings_for_date($today['date']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($config['app_name']) ?></title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
    <main class="page">
        <header class="topbar">
            <div>
                <p class="eyebrow"><?= h($today['date']) ?></p>
                <h1>Fatos historicos em contexto</h1>
            </div>
            <a href="/admin/login.php">Admin</a>
        </header>

        <?php if (!$items): ?>
            <section class="empty">
                <h2>Nenhum fato aprovado para hoje.</h2>
                <p>Acesse o painel administrativo para rodar a selecao diaria e aprovar os eventos.</p>
            </section>
        <?php endif; ?>

        <section class="list">
            <?php foreach ($items as $item): ?>
                <article class="event">
                    <div class="year"><?= h($item['year']) ?></div>
                    <div>
                        <h2><?= h($item['title']) ?></h2>
                        <p><?= h($item['description']) ?></p>
                        <p class="context"><?= h($item['context_summary']) ?></p>
                        <div class="meta">
                            <span><?= h($item['category']) ?></span>
                            <span><?= h($item['region']) ?></span>
                            <span>Score <?= h(number_format((float) $item['score'], 1)) ?></span>
                        </div>
                        <?php if ($item['source_url']): ?>
                            <a class="source" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    </main>
</body>
</html>
