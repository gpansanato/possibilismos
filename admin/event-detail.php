<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$event = $id > 0 ? event_by_id($id) : null;

if (!$event) {
    render_page_start('Evento nao encontrado', 'events', 'admin', 'O registro solicitado nao foi localizado.');
    ?>
        <section class="empty">
            <h2>Evento nao encontrado.</h2>
            <p>Volte para a listagem e selecione um item valido.</p>
            <p><a class="button" href="/admin/events.php">Voltar para eventos</a></p>
        </section>
    <?php
    render_page_end();
    exit;
}

$rankings = event_rankings((int) $event['id']);
$returnTo = '/admin/event-detail.php?id=' . (int) $event['id'];

render_page_start('Detalhes do evento', 'events', 'admin', 'Visualizacao completa do evento historico selecionado.');
?>
    <section class="section-heading">
        <div>
            <p class="eyebrow"><?= h(str_pad((string) $event['event_day'], 2, '0', STR_PAD_LEFT)) ?>/<?= h(str_pad((string) $event['event_month'], 2, '0', STR_PAD_LEFT)) ?> - <?= h($event['year']) ?></p>
            <h2><?= h($event['title']) ?></h2>
        </div>
        <a class="button button-secondary" href="/admin/events.php">Voltar</a>
    </section>

    <section class="panel">
        <div class="detail-grid">
            <div>
                <span class="eyebrow">Estado</span>
                <p><span class="status-badge <?= h(event_review_status_class($event['review_status'])) ?>"><?= h(event_review_status_label($event['review_status'])) ?></span></p>
            </div>
            <div>
                <span class="eyebrow">Score base</span>
                <p><?= h(number_format((float) $event['base_score'], 1)) ?></p>
            </div>
            <div>
                <span class="eyebrow">Categoria</span>
                <p><?= h($event['category']) ?></p>
            </div>
            <div>
                <span class="eyebrow">Regiao</span>
                <p><?= h($event['region']) ?></p>
            </div>
            <div>
                <span class="eyebrow">Ativo</span>
                <p><?= ((int) $event['active']) === 1 ? 'Sim' : 'Nao' ?></p>
            </div>
            <div>
                <span class="eyebrow">Criado em</span>
                <p><?= h($event['created_at']) ?></p>
            </div>
        </div>

        <hr class="divider">

        <h1>Descricao completa</h1>
        <p><?= h($event['description']) ?></p>

        <?php if ($event['source_url']): ?>
            <p><a class="source" href="<?= h($event['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte original</a></p>
        <?php endif; ?>

        <form class="actions" method="post" action="/admin/update-event-status.php">
            <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
            <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
            <button name="review_status" value="approved" type="submit">Aprovar</button>
            <button name="review_status" value="pending" type="submit">Marcar nao avaliado</button>
            <button class="danger" name="review_status" value="rejected" type="submit">Reprovar</button>
        </form>
    </section>

    <section class="panel">
        <h1>Historico de score</h1>
        <?php if (!$rankings): ?>
            <p>Nenhum score diario salvo para este evento.</p>
        <?php else: ?>
            <div class="table-wrap table-wrap--plain">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Score</th>
                            <th>Status ranking</th>
                            <th>Resumo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rankings as $ranking): ?>
                            <tr>
                                <td data-label="Data"><?= h($ranking['run_date']) ?></td>
                                <td data-label="Score"><?= h(number_format((float) $ranking['score'], 1)) ?></td>
                                <td data-label="Status"><?= h($ranking['status']) ?></td>
                                <td data-label="Resumo"><?= h($ranking['context_summary']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php render_page_end(); ?>
