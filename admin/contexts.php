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
$message = null;
$error = null;

$returnTo = '/admin/contexts.php';
if ($_SERVER['QUERY_STRING'] ?? '') {
    $returnTo .= '?' . $_SERVER['QUERY_STRING'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $selectedIds = array_values(array_filter(array_map('intval', $_POST['selected_ids'] ?? [])));

    try {
        if ($action === 'delete_contexts') {
            if (!$selectedIds) {
                $error = 'Selecione ao menos um contexto para excluir.';
            } else {
                $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $stmt = db()->prepare('SELECT DISTINCT run_date FROM collected_contexts WHERE id IN (' . $placeholders . ')');
                $stmt->execute($selectedIds);
                $affectedDates = array_map(static fn($row) => (string) $row['run_date'], $stmt->fetchAll());

                $delete = db()->prepare('DELETE FROM collected_contexts WHERE id = ?');
                foreach ($selectedIds as $contextId) {
                    $delete->execute([$contextId]);
                }

                foreach ($affectedDates as $affectedDate) {
                    rebuild_current_topics_from_collected_contexts($affectedDate);
                }

                $message = count($selectedIds) . ' contextos excluídos.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$items = collected_contexts_search($runDate, $selectedType, $source, $search, $sort);

$newsCount = collected_contexts_count_for_date($runDate, 'news');
$trendCount = collected_contexts_count_for_date($runDate, 'trend');

render_page_start('Notícias e tendências', 'contexts', 'admin', 'Consulta dos itens coletados, higienizados e usados como contexto do score.');
?>
    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

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
                    <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Título A-Z</option>
                    <option value="title_desc" <?= $sort === 'title_desc' ? 'selected' : '' ?>>Título Z-A</option>
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
            <p>Nenhum item persistido para os filtros selecionados. Execute novas coletas em Coletas.</p>
        </section>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <section class="bulk-toolbar">
            <span class="eyebrow">Ações em lote</span>
            <button class="danger" name="action" value="delete_contexts" type="submit" onclick="return confirm('Excluir definitivamente os contextos selecionados?')">Excluir selecionados</button>
        </section>

        <section class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" aria-label="Selecionar todos" onclick="document.querySelectorAll('.context-select').forEach(cb => cb.checked = this.checked)"></th>
                        <th><?= context_sort_link('Data', 'date_asc', 'date_desc', $sort) ?></th>
                        <th><?= context_sort_link('Tipo', 'type', 'type_desc', $sort) ?></th>
                        <th><?= context_sort_link('Título', 'title_asc', 'title_desc', $sort) ?></th>
                        <th><?= context_sort_link('Fonte', 'source', 'source_desc', $sort) ?></th>
                        <th><?= context_sort_link('Palavras-chave', 'keywords_asc', 'keywords_desc', $sort) ?></th>
                        <th><?= context_sort_link('Atualizado', 'updated_asc', 'updated_desc', $sort) ?></th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td data-label="Sel."><input class="context-select" type="checkbox" name="selected_ids[]" value="<?= h((string) $item['id']) ?>"></td>
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
                                <div class="row-actions actions-inline">
                                    <?php if ($item['source_url']): ?>
                                        <a class="button button-secondary is-compact" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                                    <?php endif; ?>
                                    <button class="icon-button danger" name="action" value="delete_contexts" onclick="this.closest('tr').querySelector('.context-select').checked=true; return confirm('Excluir definitivamente este contexto coletado?')" title="Excluir" type="submit">Excluir</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </form>
<?php render_page_end(); ?>

<?php
function context_sort_link(string $label, string $asc, string $desc, string $currentSort): string
{
    $target = $currentSort === $asc ? $desc : $asc;
    $params = $_GET;
    $params['sort'] = $target;
    $indicator = in_array($currentSort, [$asc, $desc], true) ? ($currentSort === $asc ? ' ↑' : ' ↓') : '';
    return '<a class="table-sort" href="/admin/contexts.php?' . h(http_build_query($params)) . '">' . h($label . $indicator) . '</a>';
}
