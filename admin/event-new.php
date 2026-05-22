<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = db()->prepare(
        'INSERT INTO events
         (event_month, event_day, year, title, description, category, region, source_url, base_score, review_status, active, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, NOW())'
    );
    $stmt->execute([
        (int) $_POST['event_month'],
        (int) $_POST['event_day'],
        (int) $_POST['year'],
        $_POST['title'],
        $_POST['description'],
        $_POST['category'],
        $_POST['region'],
        $_POST['source_url'] ?: null,
        (float) $_POST['base_score'],
    ]);

    redirect('/admin/events.php');
}

render_page_start('Novo evento historico', 'event-new', 'admin', 'Cadastro manual de um evento. Todo novo evento entra como nao avaliado.');
?>
    <section class="panel">
        <h1>Dados do evento</h1>
        <form class="form" method="post">
            <label>Mes <input type="number" name="event_month" min="1" max="12" required></label>
            <label>Dia <input type="number" name="event_day" min="1" max="31" required></label>
            <label>Ano <input type="number" name="year" required></label>
            <label>Titulo <input name="title" required></label>
            <label>Descricao <textarea name="description" required></textarea></label>
            <label>Categoria <input name="category" required></label>
            <label>Regiao <input name="region" required></label>
            <label>Fonte URL <input type="url" name="source_url"></label>
            <label>Relevancia base <input type="number" name="base_score" min="0" max="100" step="0.1" value="50" required></label>
            <button type="submit">Salvar evento</button>
        </form>
    </section>
<?php render_page_end(); ?>
