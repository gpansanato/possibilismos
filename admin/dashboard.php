<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

render_page_start('Painel administrativo', 'dashboard', 'admin', 'Atalhos de operacao para coletas, curadoria e priorizacao.');
?>
    <section class="option-grid" aria-label="Opcoes administrativas">
        <a class="option-card" href="/admin/sources.php">
            <span>Fontes e coletas</span>
            <strong>Executar processamentos operacionais</strong>
        </a>
        <a class="option-card" href="/admin/contexts.php">
            <span>Base higienizada</span>
            <strong>Ver noticias e tendencias persistidas</strong>
        </a>
        <a class="option-card" href="/admin/priority.php">
            <span>Analise</span>
            <strong>Ver priorizacoes e criterios</strong>
        </a>
        <a class="option-card" href="/admin/collections.php">
            <span>Status</span>
            <strong>Acompanhar coletas por data</strong>
        </a>
        <a class="option-card" href="/admin/events.php">
            <span>Base historica</span>
            <strong>Revisar eventos coletados</strong>
        </a>
        <a class="option-card" href="/">
            <span>Site publico</span>
            <strong>Ver resultado publicado</strong>
        </a>
    </section>
<?php render_page_end(); ?>
