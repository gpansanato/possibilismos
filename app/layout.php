<?php

function nav_items(string $area): array
{
    if ($area === 'admin') {
        return [
            ['key' => 'dashboard', 'label' => 'Painel', 'href' => '/admin/dashboard.php'],
            ['key' => 'events', 'label' => 'Eventos', 'href' => '/admin/events.php'],
            ['key' => 'event-new', 'label' => 'Novo evento', 'href' => '/admin/event-new.php'],
            ['key' => 'collect-events', 'label' => 'Coletar eventos', 'href' => '/admin/collect-events.php'],
            ['key' => 'collect-news', 'label' => 'Coletar noticias', 'href' => '/admin/collect-news.php'],
            ['key' => 'collect-trends', 'label' => 'Coletar tendencias', 'href' => '/admin/collect-trends.php'],
            ['key' => 'contexts', 'label' => 'Base contexto', 'href' => '/admin/contexts.php'],
            ['key' => 'apply-score', 'label' => 'Aplicar score', 'href' => '/admin/apply-score.php'],
            ['key' => 'priority', 'label' => 'Prioridade', 'href' => '/admin/priority.php'],
            ['key' => 'run', 'label' => 'Execucao completa', 'href' => '/admin/run.php'],
            ['key' => 'db-check', 'label' => 'Banco', 'href' => '/admin/db-check.php'],
            ['key' => 'site', 'label' => 'Site publico', 'href' => '/'],
            ['key' => 'logout', 'label' => 'Sair', 'href' => '/admin/logout.php'],
        ];
    }

    return [
        ['key' => 'home', 'label' => 'Inicio', 'href' => '/'],
        ['key' => 'today', 'label' => 'Fatos de hoje', 'href' => '/#fatos-de-hoje'],
        ['key' => 'admin', 'label' => 'Admin', 'href' => '/admin/login.php'],
    ];
}

function render_page_start(string $title, string $active = 'home', string $area = 'public', ?string $subtitle = null): void
{
    $config = require __DIR__ . '/config.php';
    ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?></title>
    <link rel="stylesheet" href="/public/style.css">
</head>
<body>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="/">
                <span class="brand__mark">P</span>
                <span>
                    <strong><?= h($config['app_name']) ?></strong>
                    <small>Fatos historicos em contexto</small>
                </span>
            </a>

            <nav class="main-nav" aria-label="Navegacao principal">
                <?php foreach (nav_items($area) as $item): ?>
                    <a class="<?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= h($item['href']) ?>">
                        <?= h($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main class="page">
        <header class="page-heading">
            <p class="eyebrow"><?= h($area === 'admin' ? 'Administracao' : 'Publico') ?></p>
            <h1><?= h($title) ?></h1>
            <?php if ($subtitle): ?>
                <p><?= h($subtitle) ?></p>
            <?php endif; ?>
        </header>
    <?php
}

function render_page_end(): void
{
    ?>
    </main>
</body>
</html>
    <?php
}
