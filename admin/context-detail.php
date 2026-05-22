<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$context = $id > 0 ? collected_context_by_id($id) : null;

if (!$context) {
    render_page_start('Contexto nao encontrado', 'contexts', 'admin', 'O item solicitado nao foi localizado.');
    ?>
        <section class="empty">
            <h2>Item nao encontrado.</h2>
            <p>Volte para a listagem de noticias e tendencias e selecione um item valido.</p>
            <p><a class="button" href="/admin/contexts.php">Voltar para contexto</a></p>
        </section>
    <?php
    render_page_end();
    exit;
}

render_page_start('Detalhes do contexto', 'contexts', 'admin', 'Visualizacao completa do item coletado e higienizado.');
?>
    <section class="section-heading">
        <div>
            <p class="eyebrow"><?= h($context['run_date']) ?> - <?= h($context['context_type'] === 'news' ? 'Noticia' : 'Tendencia') ?></p>
            <h2><?= h($context['title']) ?></h2>
        </div>
        <a class="button button-secondary" href="/admin/contexts.php?date=<?= h($context['run_date']) ?>&type=<?= h($context['context_type']) ?>">Voltar</a>
    </section>

    <section class="panel">
        <div class="detail-grid">
            <div>
                <span class="eyebrow">Tipo</span>
                <p><span class="status-badge <?= $context['context_type'] === 'news' ? 'is-approved' : 'is-pending' ?>"><?= h($context['context_type'] === 'news' ? 'Noticia' : 'Tendencia') ?></span></p>
            </div>
            <div>
                <span class="eyebrow">Fonte</span>
                <p><?= h($context['source']) ?></p>
            </div>
            <div>
                <span class="eyebrow">Data da coleta</span>
                <p><?= h($context['run_date']) ?></p>
            </div>
            <div>
                <span class="eyebrow">Criado em</span>
                <p><?= h($context['created_at']) ?></p>
            </div>
            <div>
                <span class="eyebrow">Atualizado em</span>
                <p><?= h($context['updated_at']) ?></p>
            </div>
            <div>
                <span class="eyebrow">ID</span>
                <p><?= h((string) $context['id']) ?></p>
            </div>
        </div>

        <hr class="divider">

        <h1>Palavras-chave</h1>
        <p><?= h($context['keywords']) ?></p>

        <h1>Texto bruto higienizado</h1>
        <p><?= h($context['raw_text']) ?></p>

        <h1>Chave normalizada</h1>
        <p><?= h($context['normalized_title']) ?></p>

        <?php if ($context['source_url']): ?>
            <p><a class="button button-secondary" href="<?= h($context['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte original</a></p>
        <?php endif; ?>
    </section>
<?php render_page_end(); ?>
