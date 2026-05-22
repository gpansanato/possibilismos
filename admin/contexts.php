<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$runDate = $_GET['date'] ?? $today['date'];
$type = $_GET['type'] ?? '';
$items = collected_contexts_for_date($runDate, in_array($type, ['news', 'trend'], true) ? $type : null);

render_page_start('Base higienizada de contexto', 'contexts', 'admin', 'Noticias e tendencias persistidas, normalizadas e deduplicadas por coleta.');
?>
    <section class="panel">
        <form class="filter-form" method="get">
            <label>Data <input type="date" name="date" value="<?= h($runDate) ?>"></label>
            <label>Tipo
                <select name="type">
                    <option value="" <?= $type === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="news" <?= $type === 'news' ? 'selected' : '' ?>>Noticias</option>
                    <option value="trend" <?= $type === 'trend' ? 'selected' : '' ?>>Tendencias</option>
                </select>
            </label>
            <button type="submit">Filtrar</button>
        </form>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow"><?= count($items) ?> itens persistidos</p>
            <h2>Itens coletados</h2>
        </div>
        <a class="button" href="/admin/collections.php">Executar coletas</a>
    </section>

    <?php if (!$items): ?>
        <section class="empty">
            <p>Nenhum item persistido para os filtros selecionados. Execute a coleta de noticias, tendencias ou contexto completo.</p>
        </section>
    <?php endif; ?>

    <section class="list">
        <?php foreach ($items as $item): ?>
            <article class="event">
                <div class="year"><?= h($item['context_type']) ?></div>
                <div>
                    <h2><?= h($item['title']) ?></h2>
                    <div class="meta">
                        <span><?= h($item['source']) ?></span>
                        <span><?= h($item['updated_at']) ?></span>
                    </div>
                    <p><?= h($item['keywords']) ?></p>
                    <?php if ($item['source_url']): ?>
                        <a href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php render_page_end(); ?>
