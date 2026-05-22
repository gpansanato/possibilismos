<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$events = db()->query('SELECT * FROM events ORDER BY event_month, event_day, active DESC, year')->fetchAll();
render_page_start('Eventos historicos', 'events', 'admin', 'Listagem completa da base historica usada pela selecao diaria.');
?>
    <section class="section-heading">
        <div>
            <p class="eyebrow"><?= count($events) ?> eventos cadastrados</p>
            <h2>Base de eventos</h2>
        </div>
        <a class="button" href="/admin/event-new.php">Novo evento</a>
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
                        <span class="status-badge <?= (int) $event['active'] === 1 ? 'is-approved' : 'is-rejected' ?>">
                            <?= (int) $event['active'] === 1 ? 'Aprovado' : 'Reprovado' ?>
                        </span>
                    </div>
                    <form class="actions" method="post" action="/admin/update-event-status.php">
                        <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                        <button name="active" value="1" type="submit">Aprovar</button>
                        <button class="danger" name="active" value="0" type="submit">Reprovar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php render_page_end(); ?>
