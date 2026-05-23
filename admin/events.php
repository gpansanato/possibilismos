<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$status = $_GET['status'] ?? 'all';
$month = $_GET['month'] ?? '';
$day = $_GET['day'] ?? '';
$search = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'date_asc';
$allowedSorts = [
    'date_asc' => 'event_month ASC, event_day ASC, year ASC',
    'date_desc' => 'event_month DESC, event_day DESC, year ASC',
    'score_desc' => 'base_score DESC, event_month ASC, event_day ASC, year ASC',
    'score_asc' => 'base_score ASC, event_month ASC, event_day ASC, year ASC',
];
if (!isset($allowedSorts[$sort])) {
    $sort = 'date_asc';
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

if ($search !== '') {
    $where[] = '(e.title LIKE ? OR e.description LIKE ? OR e.category LIKE ? OR e.region LIKE ? OR e.canonical_id LIKE ? OR e.canonical_source LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term, $term);
}

$sql = 'SELECT e.*, COALESCE(en.enrichment_count, 0) AS enrichment_count
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

render_page_start('Eventos historicos', 'events', 'admin', 'Listagem completa da base historica usada pela selecao diaria.');
$returnTo = '/admin/events.php';
if ($_SERVER['QUERY_STRING'] ?? '') {
    $returnTo .= '?' . $_SERVER['QUERY_STRING'];
}
?>
    <section class="section-heading">
        <div>
            <p class="eyebrow">
                <?= count($events) ?> eventos na lista |
                <?= h((string) $countByStatus['pending']) ?> nao avaliados |
                <?= h((string) $countByStatus['approved']) ?> aprovados |
                <?= h((string) $countByStatus['rejected']) ?> reprovados
            </p>
            <h2>Base de eventos</h2>
        </div>
    </section>

    <section class="panel">
        <form class="filter-form" method="get">
            <label>Estado
                <select name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Nao avaliados</option>
                    <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Aprovados</option>
                    <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Reprovados</option>
                </select>
            </label>
            <label>Mes <input type="number" name="month" min="1" max="12" value="<?= h($month) ?>"></label>
            <label>Dia <input type="number" name="day" min="1" max="31" value="<?= h($day) ?>"></label>
            <label>Ordenar
                <select name="sort">
                    <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>Data crescente</option>
                    <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Data decrescente</option>
                    <option value="score_desc" <?= $sort === 'score_desc' ? 'selected' : '' ?>>Score maior</option>
                    <option value="score_asc" <?= $sort === 'score_asc' ? 'selected' : '' ?>>Score menor</option>
                </select>
            </label>
            <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Titulo, descricao, categoria ou regiao"></label>
            <button type="submit">Filtrar</button>
            <a class="button button-secondary" href="/admin/events.php">Limpar</a>
        </form>
    </section>

    <section class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Ano</th>
                    <th>Evento</th>
                    <th>Categoria</th>
                    <th>Origem</th>
                    <th>Enriq.</th>
                    <th>Score</th>
                    <th>Estado</th>
                    <th>Acoes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td data-label="Data"><?= h(str_pad((string) $event['event_day'], 2, '0', STR_PAD_LEFT)) ?>/<?= h(str_pad((string) $event['event_month'], 2, '0', STR_PAD_LEFT)) ?></td>
                        <td data-label="Ano"><?= h($event['year']) ?></td>
                        <td data-label="Evento">
                            <a class="table-title" href="/admin/event-detail.php?id=<?= h((string) $event['id']) ?>"><?= h($event['title']) ?></a>
                            <small><?= h($event['region']) ?></small>
                        </td>
                        <td data-label="Categoria"><?= h($event['category']) ?></td>
                        <td data-label="Origem"><?= h($event['canonical_source'] ?: 'Wikimedia') ?></td>
                        <td data-label="Enriq."><?= h((string) $event['enrichment_count']) ?></td>
                        <td data-label="Score"><?= h(number_format((float) $event['base_score'], 1)) ?></td>
                        <td data-label="Estado">
                            <span class="status-badge <?= h(event_review_status_class($event['review_status'])) ?>">
                                <?= h(event_review_status_label($event['review_status'])) ?>
                            </span>
                        </td>
                        <td data-label="Acoes">
                            <form class="row-actions" method="post" action="/admin/update-event-status.php">
                                <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                                <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                                <a class="button button-secondary" href="/admin/event-detail.php?id=<?= h((string) $event['id']) ?>">Detalhes</a>
                                <button name="review_status" value="approved" type="submit">Aprovar</button>
                                <button name="review_status" value="pending" type="submit">Pendente</button>
                                <button class="danger" name="review_status" value="rejected" type="submit">Reprovar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php render_page_end(); ?>
