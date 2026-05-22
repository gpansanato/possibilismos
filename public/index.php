<?php
require_once __DIR__ . '/../app/bootstrap.php';

$today = today_key();
$items = published_rankings_for_date($today['date']);
render_page_start('Inicio', 'home', 'public', 'Selecao diaria de fatos historicos conectados aos temas atuais.');
?>
        <section class="option-grid" aria-label="Opcoes de navegacao">
            <a class="option-card" href="#fatos-de-hoje">
                <span>Fatos de hoje</span>
                <strong>Ver publicacoes aprovadas</strong>
            </a>
            <a class="option-card" href="/admin/login.php">
                <span>Admin</span>
                <strong>Acessar curadoria</strong>
            </a>
        </section>

        <section id="fatos-de-hoje" class="section-heading">
            <div>
                <p class="eyebrow"><?= h($today['date']) ?></p>
                <h2>Fatos aprovados</h2>
            </div>
        </section>

        <?php if (!$items): ?>
            <section class="empty">
                <h2>Nenhum fato aprovado para hoje.</h2>
                <p>Acesse o painel administrativo para rodar a selecao diaria e aprovar os eventos.</p>
            </section>
        <?php endif; ?>

        <section class="list">
            <?php foreach ($items as $item): ?>
                <article class="event">
                    <div class="year"><?= h($item['year']) ?></div>
                    <div>
                        <h2><?= h($item['title']) ?></h2>
                        <p><?= h($item['description']) ?></p>
                        <p class="context"><?= h($item['context_summary']) ?></p>
                        <div class="meta">
                            <span><?= h($item['category']) ?></span>
                            <span><?= h($item['region']) ?></span>
                            <span>Score <?= h(number_format((float) $item['score'], 1)) ?></span>
                        </div>
                        <?php if ($item['source_url']): ?>
                            <a class="source" href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Fonte</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
<?php render_page_end(); ?>
