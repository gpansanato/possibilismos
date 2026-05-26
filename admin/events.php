<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$message = null;
$error = null;
$status = $_GET['status'] ?? 'all';
$date = $_GET['date'] ?? '';
$month = $_GET['month'] ?? '';
$day = $_GET['day'] ?? '';
$enrichment = $_GET['enrichment'] ?? 'all';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_asc';
$allowedSorts = [
    'date_asc' => 'event_month ASC, event_day ASC, year ASC',
    'date_desc' => 'event_month DESC, event_day DESC, year ASC',
    'year_asc' => 'year ASC, event_month ASC, event_day ASC',
    'year_desc' => 'year DESC, event_month ASC, event_day ASC',
    'title_asc' => 'title ASC, year ASC',
    'title_desc' => 'title DESC, year ASC',
    'category_asc' => 'category ASC, title ASC',
    'source_asc' => 'canonical_source ASC, title ASC',
    'enrichment_desc' => 'enriched_at DESC, enrichment_count DESC, title ASC',
    'enrichment_asc' => 'enriched_at ASC, enrichment_count ASC, title ASC',
    'priority_desc' => 'priority_score DESC, event_month ASC, event_day ASC, year ASC',
    'priority_asc' => 'priority_score ASC, event_month ASC, event_day ASC, year ASC',
    'status_asc' => 'review_status ASC, title ASC',
    'score_desc' => 'priority_score DESC, event_month ASC, event_day ASC, year ASC',
    'score_asc' => 'priority_score ASC, event_month ASC, event_day ASC, year ASC',
];
if (!isset($allowedSorts[$sort])) {
    $sort = 'date_asc';
}

if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $parts = date_parts_from_run_date($date);
    $month = (string) $parts['month'];
    $day = (string) $parts['day'];
} elseif ($date === '') {
    $date = sprintf('%04d-%02d-%02d', (int) date('Y'), $month !== '' ? (int) $month : $today['month'], $day !== '' ? (int) $day : $today['day']);
}

$returnTo = '/admin/events.php';
if ($_SERVER['QUERY_STRING'] ?? '') {
    $returnTo .= '?' . $_SERVER['QUERY_STRING'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $actionDate = $_POST['date'] ?? $date;
    if (!is_string($actionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
        $actionDate = $today['date'];
    }
    $selectedIds = array_values(array_filter(array_map('intval', $_POST['selected_ids'] ?? [])));
    $postReturnTo = $_POST['return_to'] ?? ('/admin/events.php?date=' . $actionDate);
    if (!is_string($postReturnTo) || strpos($postReturnTo, '/admin/') !== 0) {
        $postReturnTo = '/admin/events.php?date=' . $actionDate;
    }

    try {
        if (in_array($action, ['bulk_publish', 'bulk_reject'], true)) {
            $reviewStatus = $action === 'bulk_publish' ? 'approved' : 'rejected';
            if (!$selectedIds) {
                $error = 'Selecione ao menos um evento para aplicar a ação.';
            } else {
                $stmt = db()->prepare('UPDATE events SET review_status = ?, active = ? WHERE id = ?');
                foreach ($selectedIds as $eventId) {
                    $stmt->execute([$reviewStatus, event_active_from_review_status($reviewStatus), $eventId]);
                }
                $message = count($selectedIds) . ' eventos atualizados.';
            }
        } elseif ($action === 'delete_events') {
            if (!$selectedIds) {
                $error = 'Selecione ao menos um evento para excluir.';
            } else {
                $unlinkImports = db()->prepare('UPDATE event_imports SET canonical_event_id = NULL, status = "ignored", updated_at = NOW() WHERE canonical_event_id = ?');
                $stmt = db()->prepare('DELETE FROM events WHERE id = ?');
                foreach ($selectedIds as $eventId) {
                    $unlinkImports->execute([$eventId]);
                    $stmt->execute([$eventId]);
                }
                $message = count($selectedIds) . ' eventos excluídos.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$where = [];
$params = [];

if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
    $where[] = 'review_status = ?';
    $params[] = $status;
}

if ($month !== '') {
    $where[] = 'event_month = ?';
    $params[] = (int) $month;
}

if ($day !== '') {
    $where[] = 'event_day = ?';
    $params[] = (int) $day;
}

if ($enrichment === 'enriched') {
    $where[] = 'e.enriched_at IS NOT NULL';
} elseif ($enrichment === 'not_enriched') {
    $where[] = 'e.enriched_at IS NULL';
} else {
    $enrichment = 'all';
}

if ($search !== '') {
    $where[] = '(e.title LIKE ? OR e.description LIKE ? OR e.category LIKE ? OR e.region LIKE ? OR e.canonical_id LIKE ? OR e.canonical_source LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}

$sql = 'SELECT e.*, COALESCE(en.enrichment_count, 0) AS enrichment_count,
            COALESCE((
                SELECT dr.score
                FROM daily_rankings dr
                WHERE dr.event_id = e.id
                ORDER BY dr.run_date DESC, dr.id DESC
                LIMIT 1
            ), 0) AS priority_score
        FROM events e
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS enrichment_count
            FROM event_enrichments
            GROUP BY event_id
        ) en ON en.event_id = e.id';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY ' . $allowedSorts[$sort] . ', FIELD(review_status, "pending", "approved", "rejected")';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();

$counts = db()->query(
    'SELECT review_status, COUNT(*) total FROM events GROUP BY review_status'
)->fetchAll();
$countByStatus = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($counts as $row) {
    $countByStatus[$row['review_status']] = (int) $row['total'];
}

render_page_start('Eventos históricos', 'events', 'admin', 'Consulta, curadoria e gestão editorial dos fatos históricos coletados.');
?>
    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

    <section class="panel">
        <form class="filter-form filter-form--events" method="get">
            <label>Data dos fatos <input type="date" name="date" value="<?= h($date) ?>"></label>
            <label>Estado
                <select name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Não publicados</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Publicados</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Reprovados</option>
                </select>
            </label>
            <label>Enriquecimento
                <select name="enrichment">
                    <option value="all" <?= $enrichment === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="enriched" <?= $enrichment === 'enriched' ? 'selected' : '' ?>>Enriquecidos</option>
                    <option value="not_enriched" <?= $enrichment === 'not_enriched' ? 'selected' : '' ?>>Não enriquecidos</option>
                </select>
            </label>
            <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Título, descrição, categoria ou região"></label>
            <button type="submit">Filtrar</button>
            <a class="button button-secondary" href="/admin/events.php">Hoje</a>
            <a class="button button-secondary" href="/admin/events.php?date=<?= h($date) ?>">Limpar</a>
        </form>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow">
                <?= count($events) ?> eventos |
                <?= h((string) $countByStatus['pending']) ?> não publicados |
                <?= h((string) $countByStatus['approved']) ?> publicados |
                <?= h((string) $countByStatus['rejected']) ?> reprovados
            </p>
            <h2>Base de eventos</h2>
        </div>
    </section>

    <form method="post">
        <input type="hidden" name="date" value="<?= h($date) ?>">
        <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
        <section class="bulk-toolbar">
            <span class="eyebrow">Ações em lote</span>
            <button name="action" value="bulk_publish" type="submit">Publicar selecionados</button>
            <button class="button-secondary" name="action" value="bulk_reject" type="submit">Reprovar selecionados</button>
            <button class="danger" name="action" value="delete_events" type="submit" onclick="return confirm('Excluir definitivamente os eventos selecionados?')">Excluir selecionados</button>
        </section>

        <section class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" aria-label="Selecionar todos" onclick="document.querySelectorAll('.event-select').forEach(cb => cb.checked = this.checked)"></th>
                        <th><?= sort_link('Data/Ano', 'date_asc', 'date_desc', $sort) ?></th>
                        <th><?= sort_link('Evento', 'title_asc', 'title_desc', $sort) ?></th>
                        <th><?= sort_link('Categoria', 'category_asc', 'category_asc', $sort) ?></th>
                        <th><?= sort_link('Origem', 'source_asc', 'source_asc', $sort) ?></th>
                        <th><?= sort_link('Enriquecido', 'enrichment_desc', 'enrichment_asc', $sort) ?></th>
                        <th><?= sort_link('Prioridade', 'priority_desc', 'priority_asc', $sort) ?></th>
                        <th><?= sort_link('Estado', 'status_asc', 'status_asc', $sort) ?></th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($events as $event): ?>
                        <tr>
                            <td data-label="Sel."><input class="event-select" type="checkbox" name="selected_ids[]" value="<?= h((string) $event['id']) ?>"></td>
                            <td data-label="Data/Ano">
                                <strong><?= h(str_pad((string) $event['event_day'], 2, '0', STR_PAD_LEFT)) ?>/<?= h(str_pad((string) $event['event_month'], 2, '0', STR_PAD_LEFT)) ?></strong>
                                <small><?= h(format_event_year((int) $event['year'])) ?></small>
                            </td>
                            <td data-label="Evento">
                                <a class="table-title" href="/admin/event-detail.php?id=<?= h((string) $event['id']) ?>"><?= h($event['title']) ?></a>
                                <?php if ($event['region'] && $event['region'] !== ($event['canonical_source'] ?: 'Wikimedia')): ?>
                                    <small><?= h($event['region']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td data-label="Categoria"><?= h($event['category']) ?></td>
                            <td data-label="Origem"><?= h($event['canonical_source'] ?: 'Wikimedia') ?></td>
                            <td data-label="Enriquecido">
                                <span class="status-badge <?= !empty($event['enriched_at']) ? 'is-approved' : 'is-pending' ?>">
                                    <?= !empty($event['enriched_at']) ? 'Sim' : 'Não' ?>
                                </span>
                                <small><?= h((string) $event['enrichment_count']) ?></small>
                            </td>
                            <td data-label="Prioridade"><?= h(number_format((float) $event['priority_score'], 1)) ?></td>
                            <td data-label="Estado">
                                <span class="status-badge <?= h(event_review_status_class($event['review_status'])) ?>">
                                    <?= h(event_review_status_label($event['review_status'])) ?>
                                </span>
                            </td>
                            <td data-label="Ações">
                                <div class="row-actions actions-inline">
                                    <button class="icon-button" name="action" value="bulk_publish" onclick="this.closest('tr').querySelector('.event-select').checked=true" title="Publicar" type="submit">Publicar</button>
                                    <button class="icon-button danger" name="action" value="bulk_reject" onclick="this.closest('tr').querySelector('.event-select').checked=true" title="Reprovar" type="submit">Reprovar</button>
                                    <button class="icon-button danger" name="action" value="delete_events" onclick="this.closest('tr').querySelector('.event-select').checked=true; return confirm('Excluir definitivamente este evento coletado?')" title="Excluir" type="submit">Excluir</button>
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
function sort_link(string $label, string $asc, string $desc, string $currentSort): string
{
    $target = $currentSort === $asc ? $desc : $asc;
    $params = $_GET;
    $params['sort'] = $target;
    $indicator = in_array($currentSort, [$asc, $desc], true) ? ($currentSort === $asc ? ' ↑' : ' ↓') : '';
    return '<a class="table-sort" href="/admin/events.php?' . h(http_build_query($params)) . '">' . h($label . $indicator) . '</a>';
}

function format_event_year(int $year): string
{
    if ($year < 0) {
        return abs($year) . ' a.C.';
    }

    return (string) $year;
}
