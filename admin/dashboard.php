<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$rankings = rankings_for_date($today['date']);
render_page_start('Painel administrativo', 'dashboard', 'admin', 'Atalhos de operacao e sugestoes geradas para hoje.');
?>
    <section class="option-grid" aria-label="Opcoes administrativas">
        <a class="option-card" href="/admin/collect-events.php">
            <span>Etapa 1</span>
            <strong>Coletar eventos historicos</strong>
        </a>
        <a class="option-card" href="/admin/collect-news.php">
            <span>Etapa 2</span>
            <strong>Coletar noticias do dia</strong>
        </a>
        <a class="option-card" href="/admin/apply-score.php">
            <span>Etapa 3</span>
            <strong>Aplicar score de prioridade</strong>
        </a>
        <a class="option-card" href="/admin/run.php">
            <span>Automacao</span>
            <strong>Executar processo completo</strong>
        </a>
        <a class="option-card" href="/admin/events.php">
            <span>Base historica</span>
            <strong>Cadastrar ou revisar eventos</strong>
        </a>
        <a class="option-card" href="/admin/event-new.php">
            <span>Cadastro manual</span>
            <strong>Adicionar novo evento</strong>
        </a>
        <a class="option-card" href="/">
            <span>Site publico</span>
            <strong>Ver resultado publicado</strong>
        </a>
    </section>

    <section class="section-heading">
        <div>
            <p class="eyebrow"><?= h($today['date']) ?></p>
            <h2>Sugestoes de hoje</h2>
        </div>
    </section>
    <?php if (!$rankings): ?>
        <section class="empty">
            <p>Nenhuma sugestao gerada. Rode a selecao diaria.</p>
        </section>
    <?php endif; ?>

    <section class="list">
        <?php foreach ($rankings as $item): ?>
            <article class="event">
                <div class="year"><?= h($item['year']) ?></div>
                <div>
                    <h2><?= h($item['title']) ?></h2>
                    <p><?= h($item['description']) ?></p>
                    <p class="context"><?= h($item['context_summary']) ?></p>
                    <p class="meta">
                        Publicacao: <?= h($item['status']) ?> |
                        Evento: <?= h(event_review_status_label($item['review_status'])) ?> |
                        Score: <?= h(number_format((float) $item['score'], 1)) ?>
                    </p>
                    <form method="post" action="/admin/update-ranking.php">
                        <input type="hidden" name="id" value="<?= h($item['id']) ?>">
                        <button name="status" value="approved" type="submit">Aprovar</button>
                        <button class="danger" name="status" value="rejected" type="submit">Rejeitar</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php render_page_end(); ?>
