<?php

function nav_items(string $area): array
{
    if ($area === 'admin') {
        return [
            ['key' => 'collections', 'label' => 'Coletas', 'href' => '/admin/collections.php'],
            ['key' => 'events', 'label' => 'Eventos', 'href' => '/admin/events.php'],
            ['key' => 'contexts', 'label' => 'Contexto', 'href' => '/admin/contexts.php'],
            ['key' => 'db-check', 'label' => 'Banco', 'href' => '/admin/db-check.php'],
            ['key' => 'site', 'label' => 'Site', 'href' => '/'],
            ['key' => 'logout', 'label' => 'Sair', 'href' => '/admin/logout.php'],
        ];
    }

    return [
        ['key' => 'home', 'label' => 'Início', 'href' => '/'],
        ['key' => 'events', 'label' => 'Eventos publicados', 'href' => '/eventos.php'],
        ['key' => 'admin', 'label' => 'Admin', 'href' => '/admin/login.php'],
    ];
}

function render_page_start(string $title, string $active = 'home', string $area = 'public', ?string $subtitle = null, bool $showHeading = true): void
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
                    <small>Curadoria histórica editorial</small>
                </span>
            </a>

            <nav class="main-nav" aria-label="Navegação principal">
                <?php foreach (nav_items($area) as $item): ?>
                    <a class="<?= $active === $item['key'] ? 'is-active' : '' ?>" href="<?= h($item['href']) ?>">
                        <?= h($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <main class="page">
        <?php if ($showHeading): ?>
            <header class="page-heading">
                <p class="eyebrow"><?= h($area === 'admin' ? 'Administração' : 'Público') ?></p>
                <h1><?= h($title) ?></h1>
                <?php if ($subtitle): ?>
                    <p><?= h($subtitle) ?></p>
                <?php endif; ?>
            </header>
        <?php endif; ?>
    <?php
}

function render_page_end(): void
{
    $config = require __DIR__ . '/config.php';
    ?>
    </main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div>
                <strong><?= h($config['app_name']) ?></strong>
                <p>Esteira editorial para fatos históricos contextualizados.</p>
            </div>
            <nav aria-label="Links de rodapé">
                <a href="/">Produto</a>
                <a href="/eventos.php">Publicações</a>
                <a href="/admin/login.php">Admin</a>
                <a href="/admin/db-check.php">Status</a>
            </nav>
        </div>
    </footer>
</body>
</html>
    <?php
}
