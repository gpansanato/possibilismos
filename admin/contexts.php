<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$runDate = $_GET['date'] ?? $today['date'];
$type = $_GET['type'] ?? '';
$source = trim($_GET['source'] ?? '');
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'updated_desc';
$selectedType = in_array($type, ['news', 'trend'], true) ? $type : null;
$items = collected_contexts_search($runDate, $selectedType, $source, $search, $sort);

$newsCount = collected_contexts_count_for_date($runDate, 'news');
$trendCount = collected_contexts_count_for_date($runDate, 'trend');

render_page_start('Noticias e tendencias', 'contexts', 'admin', 'Listagem compacta dos itens coletados, higienizados e usados como contexto do score.');
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
            <label>Fonte <input name="source" value="<?= h($source) ?>" placeholder="rss, gdelt, hacker..."></label>
            <label>Ordenar
                <select name="sort">
                    <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Atualizacao recente</option>
                    <option value="updated_asc" <?= $sort === 'updated_asc' ? 'selected' : '' ?>>Atualizacao antiga</option>
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Data decrescente</option>
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Data crescente</option>
                    <option value="type" <?= $sort === 'type' ? 'selected' : '' ?>>Tipo</option>
                    <option value="source" <?= $sort === 'source' ? 'selected' : '' ?>>Fonte</option>
                </select>
            </label>
            <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Titulo, palavra-chave ou texto"></label>
            <button type="submit">Filtrar</button>
            <a class="button button-secondary" href="/admin/contexts.php">Limpar</a>
        </form>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow">
                <?= count($items) ?> itens na lista |
                <?= h((string) $newsCount) ?> noticias |
                <?= h((string) $trendCount) ?> tendencias
            </p>
            <h2>Coletas de contexto</h2>
        </div>
        <a class="button" href="/admin/collections.php">Executar coletas</a>
    </section>

    <?php if (!$items): ?>
        <section class="empty">
            <p>Nenhum item persistido para os filtros selecionados. Execute a coleta de noticias, tendencias ou contexto completo.</p>
        </section>
    <?php endif; ?>

    <section class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Titulo</th>
                    <th>Fonte</th>
                    <th>Palavras-chave</th>
                    <th>Atualizado</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td data-label="Data"><?= h($item['run_date']) ?></td>
                        <td data-label="Tipo">
                            <span class="status-badge <?= $item['context_type'] === 'news' ? 'is-approved' : 'is-pending' ?>">
                                <?= h($item['context_type'] === 'news' ? 'Noticia' : 'Tendencia') ?>
                            </span>
                        </td>
                        <td data-label="Titulo">
                            <a class="table-title" href="/admin/context-detail.php?id=<?= h((string) $item['id']) ?>"><?= h($item['title']) ?></a>
                        </td>
                        <td data-label="Fonte"><?= h($item['source']) ?></td>
                        <td data-label="Palavras-chave"><?= h($item['keywords']) ?></td>
                        <td data-label="Atualizado"><?= h($item['updated_at']) ?></td>
                        <td data-label="Acoes">
                            <div class="row-actions">
                                <a class="button button-secondary" href="/admin/context-detail.php?id=<?= h((string) $item['id']) ?>">Detalhes</a>
                                <?php if ($item['source_url']): ?>
                                    <a class="button button-secondary" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php render_page_end(); ?>
