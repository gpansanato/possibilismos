<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$status = $_GET['status'] ?? 'all';
$month = $_GET['month'] ?? '';
$day = $_GET['day'] ?? '';
$search = trim($_GET['q'] ?? '');

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
    $where[] = '(title LIKE ? OR description LIKE ? OR category LIKE ? OR region LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql = 'SELECT * FROM events';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY event_month, event_day, FIELD(review_status, "pending", "approved", "rejected"), year';

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
            <label>Busca <input name="q" value="<?= h($search) ?>" placeholder="Titulo, descricao, categoria ou regiao"></label>
            <button type="submit">Filtrar</button>
            <a class="button button-secondary" href="/admin/events.php">Limpar</a>
        </form>
    </section>

    <section class="list">
        <?php foreach ($events as $event): ?>
            <article class="event">
                <div class="year"><?= h($event['year']) ?></div>
                <div>
                    <h2><?= h($event['title']) ?></h2>
                    <p><?= h($event['description']) ?></p>
                    <div class="meta">
                        <span><?= h($event['event_day']) ?>/<?= h($event['event_month']) ?></span>
                        <span><?= h($event['category']) ?></span>
                        <span><?= h($event['region']) ?></span>
                        <span>Score <?= h(number_format((float) $event['base_score'], 1)) ?></span>
                        <span class="status-badge <?= h(event_review_status_class($event['review_status'])) ?>">
                            <?= h(event_review_status_label($event['review_status'])) ?>
                        </span>
                    </div>
                    <form class="actions" method="post" action="/admin/update-event-status.php">
                        <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                        <button name="review_status" value="approved" type="submit">Aprovar</button>
                        <button name="review_status" value="pending" type="submit">Marcar nao avaliado</button>
                        <button class="danger" name="review_status" value="rejected" type="submit">Reprovar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php render_page_end(); ?>
