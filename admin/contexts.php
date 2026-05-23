<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$message = null;
$error = null;
$runDate = $_GET['date'] ?? $today['date'];
$type = $_GET['type'] ?? '';
$source = trim($_GET['source'] ?? '');
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'updated_desc';
$selectedType = in_array($type, ['news', 'trend'], true) ? $type : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $actionDate = $_POST['date'] ?? $runDate;
    if (!is_string($actionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
        $actionDate = $today['date'];
    }

    try {
        if ($action === 'news') {
            $items = collect_daily_news_topics($actionDate);
            $message = count($items) . ' notícias persistidas/coletadas.';
        } elseif ($action === 'trends') {
            $items = collect_daily_trend_topics($actionDate);
            $message = count($items) . ' tendências persistidas/coletadas.';
        } elseif ($action === 'context') {
            $news = collect_daily_news_topics($actionDate);
            $trends = collect_daily_trend_topics($actionDate);
            $message = count($news) . ' notícias e ' . count($trends) . ' tendências persistidas/coletadas.';
        }
        $runDate = $actionDate;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$items = collected_contexts_search($runDate, $selectedType, $source, $search, $sort);

$newsCount = collected_contexts_count_for_date($runDate, 'news');
$trendCount = collected_contexts_count_for_date($runDate, 'trend');

render_page_start('Notícias e tendências', 'contexts', 'admin', 'Listagem compacta dos itens coletados, higienizados e usados como contexto do score.');
?>
    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

    <section class="panel admin-toolbar">
        <form class="date-filter" method="get">
            <label>Data do contexto <input type="date" name="date" value="<?= h($runDate) ?>"></label>
            <button type="submit">Filtrar data</button>
            <a class="button button-secondary" href="/admin/contexts.php">Hoje</a>
        </form>
        <form class="actions actions-inline" method="post">
            <input type="hidden" name="date" value="<?= h($runDate) ?>">
            <button name="action" value="news" type="submit">Coletar notícias</button>
            <button class="button-secondary" name="action" value="trends" type="submit">Coletar tendências</button>
            <button class="button-secondary" name="action" value="context" type="submit">Coletar contexto</button>
        </form>
    </section>

    <section class="panel">
        <form class="filter-form" method="get">
            <label>Data <input type="date" name="date" value="<?= h($runDate) ?>"></label>
            <label>Tipo
                <select name="type">
                    <option value="" <?= $type === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="news" <?= $type === 'news' ? 'selected' : '' ?>>Notícias</option>
                    <option value="trend" <?= $type === 'trend' ? 'selected' : '' ?>>Tendências</option>
                </select>
            </label>
            <label>Fonte <input name="source" value="<?= h($source) ?>" placeholder="rss, gdelt, hacker..."></label>
            <label>Ordenar
                <select name="sort">
                    <option value="updated_desc" <?= $sort === 'updated_desc' ? 'selected' : '' ?>>Atualização recente</option>
                    <option value="updated_asc" <?= $sort === 'updated_asc' ? 'selected' : '' ?>>Atualização antiga</option>
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Data decrescente</option>
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Data crescente</option>
                    <option value="type" <?= $sort === 'type' ? 'selected' : '' ?>>Tipo</option>
                    <option value="source" <?= $sort === 'source' ? 'selected' : '' ?>>Fonte</option>
                </select>
            </label>
            <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Título, palavra-chave ou texto"></label>
            <button type="submit">Filtrar</button>
            <a class="button button-secondary" href="/admin/contexts.php">Limpar</a>
        </form>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow">
                <?= count($items) ?> itens na lista |
                <?= h((string) $newsCount) ?> notícias |
                <?= h((string) $trendCount) ?> tendências
            </p>
            <h2>Coletas de contexto</h2>
        </div>
    </section>

    <?php if (!$items): ?>
        <section class="empty">
            <p>Nenhum item persistido para os filtros selecionados. Execute a coleta de notícias, tendências ou contexto completo.</p>
        </section>
    <?php endif; ?>

    <section class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Tipo</th>
                    <th>Título</th>
                    <th>Fonte</th>
                    <th>Palavras-chave</th>
                    <th>Atualizado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td data-label="Data"><?= h($item['run_date']) ?></td>
                        <td data-label="Tipo">
                            <span class="status-badge <?= $item['context_type'] === 'news' ? 'is-approved' : 'is-pending' ?>">
                                <?= h($item['context_type'] === 'news' ? 'Notícia' : 'Tendência') ?>
                            </span>
                        </td>
                        <td data-label="Título">
                            <a class="table-title" href="/admin/context-detail.php?id=<?= h((string) $item['id']) ?>"><?= h($item['title']) ?></a>
                        </td>
                        <td data-label="Fonte"><?= h($item['source']) ?></td>
                        <td data-label="Palavras-chave"><?= h($item['keywords']) ?></td>
                        <td data-label="Atualizado"><?= h($item['updated_at']) ?></td>
                        <td data-label="Ações">
                            <div class="row-actions">
                                <?php if ($item['source_url']): ?>
                                    <a class="button button-secondary is-compact" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php render_page_end(); ?>
