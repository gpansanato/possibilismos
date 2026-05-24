<?php
require_once __DIR__ . '/../app/bootstrap.php';

render_page_start('Histórico Diário', 'home', 'public', null, false);
?>
    <section class="hero">
        <div class="hero__content">
            <?php component_badge('Plataforma de curadoria editorial histórica'); ?>
            <h1>Do fato histórico bruto ao insumo editorial pronto para revisão.</h1>
            <p>Identifique fatos relevantes para o contexto do dia, entenda por que eles importam agora e gere material de apoio para pautas, newsletters, posts e roteiros.</p>
            <div class="hero__actions">
                <a class="button button-primary" href="/eventos.php">Ver publicações</a>
                <a class="button button-secondary" href="/admin/login.php">Área administrativa</a>
            </div>
        </div>

        <aside class="mock-window" aria-label="Mockup do painel de priorização">
            <div class="mock-window__bar">
                <span></span><span></span><span></span>
                <strong>Esteira editorial</strong>
            </div>
            <div class="mock-grid">
                <?php component_metric('Fatos', '128'); ?>
                <?php component_metric('Contextos', '42'); ?>
                <?php component_metric('Insumos', '18'); ?>
            </div>
            <?php component_mock_row('Terremoto de Valdivia', 'Prioridade 84.5 - ciência e impacto social', 'Revisar'); ?>
            <?php component_mock_row('Pacto de Aço', 'Relação com política internacional atual', 'Aprovado'); ?>
            <?php component_mock_row('Arthur Conan Doyle', 'Gancho cultural para newsletter', 'Pendente'); ?>
            <div class="mock-bars">
                <span style="width: 84%"></span>
                <span style="width: 68%"></span>
                <span style="width: 52%"></span>
            </div>
        </aside>
    </section>

    <section class="landing-section">
        <div class="section-copy">
            <?php component_badge('Público prioritário'); ?>
            <h2>Para equipes que precisam transformar história em pauta com critério.</h2>
            <p>Histórico Diário apoia operações editoriais, newsletters, produtores de conteúdo, equipes de pesquisa e curadoria que precisam reduzir busca manual e decidir melhor o que publicar.</p>
        </div>
        <div class="feature-grid feature-grid--three">
            <article class="feature-card">
                <span class="feature-icon">01</span>
                <h3>Operações editoriais</h3>
                <p>Organize uma fila diária de fatos com status, fonte, justificativa e prioridade para revisão.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">02</span>
                <h3>Newsletters e conteúdo</h3>
                <p>Encontre ganchos históricos relevantes para newsletters, posts, roteiros, calendários editoriais e pautas recorrentes.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">03</span>
                <h3>Pesquisa e curadoria</h3>
                <p>Compare fatos históricos com sinais do dia para entender conexões atuais antes da decisão editorial.</p>
            </article>
        </div>
    </section>

    <section class="landing-section">
        <div class="section-copy">
            <?php component_badge('Fluxo editorial'); ?>
            <h2>Uma esteira clara da coleta à publicação.</h2>
            <p>Cada etapa existe para transformar registros históricos dispersos em insumos editoriais rastreáveis, explicáveis e prontos para curadoria.</p>
        </div>
        <div class="feature-grid">
            <article class="feature-card"><span class="feature-icon">1</span><h3>Coleta fatos históricos</h3><p>Busca eventos associados ao dia e preserva origem, data, categoria e referência para auditoria.</p></article>
            <article class="feature-card"><span class="feature-icon">2</span><h3>Higieniza e enriquece</h3><p>Complementa o fato com descrição, entidades, fontes enciclopédicas e materiais de apoio quando disponíveis.</p></article>
            <article class="feature-card"><span class="feature-icon">3</span><h3>Cruza com sinais do dia</h3><p>Notícias e tendências entram como insumos de contexto para revelar temas que podem tornar um fato relevante agora.</p></article>
            <article class="feature-card"><span class="feature-icon">4</span><h3>Calcula prioridade explicável</h3><p>O ranking indica força editorial e registra os motivos para facilitar revisão e discussão da pauta.</p></article>
            <article class="feature-card"><span class="feature-icon">5</span><h3>Apoia revisão editorial</h3><p>A curadoria aprova, reprova ou mantém pendências antes de qualquer publicação pública.</p></article>
            <article class="feature-card"><span class="feature-icon">6</span><h3>Gera insumos publicáveis</h3><p>Entrega fatos priorizados com contexto, justificativa, fonte e status para publicação ou reaproveitamento editorial.</p></article>
        </div>
    </section>

    <section class="product-section">
        <div class="section-copy">
            <?php component_badge('Produto em ação'); ?>
            <h2>Painel de decisão para a rotina editorial.</h2>
            <p>A operação acompanha coletas, enriquecimentos, contexto do dia, prioridade calculada e estado de revisão em uma única esteira.</p>
        </div>
        <div class="dashboard-mockup">
            <div class="mock-window__bar">
                <span></span><span></span><span></span>
                <strong>Operação diária</strong>
            </div>
            <div class="dashboard-mockup__body">
                <aside>
                    <b>Esteira</b>
                    <small>Fatos históricos</small>
                    <small>Enriquecimento</small>
                    <small>Contexto editorial</small>
                    <small>Priorização</small>
                </aside>
                <div>
                    <?php component_mock_row('Fato aprovado', 'justificativa pronta para revisão', 'Prioridade 91'); ?>
                    <?php component_mock_row('Fato pendente', 'aguardando curadoria editorial', 'Pendente'); ?>
                    <?php component_mock_row('Contexto higienizado', 'notícias e tendências como sinais de apoio', 'Atualizado'); ?>
                    <?php component_mock_row('Publicação diária', '5 insumos selecionados', 'Pronto'); ?>
                </div>
            </div>
        </div>
    </section>

    <section class="split-section">
        <div class="mobile-mockup" aria-label="Mockup mobile">
            <div class="mobile-mockup__screen">
                <span class="badge">Hoje</span>
                <h3>5 insumos</h3>
                <p>Prioridade, motivo e status editorial em uma visualização compacta.</p>
                <div class="mini-list"></div>
                <div class="mini-list is-short"></div>
                <div class="mini-list"></div>
            </div>
        </div>
        <div class="section-copy">
            <?php component_badge('Operação contínua'); ?>
            <h2>Revisão rápida sem perder rastreabilidade.</h2>
            <ul class="clean-list">
                <li>Acesse a fila editorial pelo painel administrativo.</li>
                <li>Veja fatos publicados, fontes e motivos de priorização.</li>
                <li>Use filtros para navegar por datas, categorias e regiões.</li>
                <li>Mantenha separadas a revisão interna e a visualização pública.</li>
            </ul>
        </div>
    </section>

    <section class="landing-section">
        <div class="section-copy">
            <?php component_badge('Adoção'); ?>
            <h2>Comece com curadoria assistida e evolua o processo.</h2>
        </div>
        <div class="feature-grid feature-grid--three">
            <article class="feature-card"><h3>Configurar fontes</h3><p>Use as fontes padrão ou ajuste os sinais de contexto conforme a linha editorial.</p></article>
            <article class="feature-card"><h3>Revisar pendências</h3><p>Fatos entram como não avaliados para evitar publicação acidental e preservar decisão humana.</p></article>
            <article class="feature-card"><h3>Calibrar prioridade</h3><p>Parâmetros permitem ajustar pesos e critérios sem refatorar a aplicação.</p></article>
        </div>
    </section>

    <section class="comparison-section">
        <div class="section-copy">
            <?php component_badge('Implantação'); ?>
            <h2>Modelos de uso para diferentes maturidades editoriais.</h2>
        </div>
        <div class="feature-grid feature-grid--three">
            <article class="offer-card"><h3>MVP hospedado</h3><p>PHP + MySQL com operação manual assistida para validar a rotina.</p><strong>Validação rápida</strong></article>
            <article class="offer-card is-featured"><h3>Operação editorial</h3><p>Coleta diária, revisão, priorização explicável e publicação controlada.</p><strong>Uso recomendado</strong></article>
            <article class="offer-card"><h3>Escala futura</h3><p>Fila de coletas, IA externa, logs, automações e integrações com canais editoriais.</p><strong>Evolução</strong></article>
        </div>
    </section>

    <section class="final-cta">
        <div>
            <?php component_badge('Próximo ciclo'); ?>
            <h2>Priorize fatos históricos com critério, fonte e justificativa.</h2>
            <p>Reduza a busca manual, entenda por que um evento importa hoje e leve para revisão apenas os fatos com melhor potencial editorial.</p>
        </div>
        <div class="hero__actions">
            <a class="button button-primary" href="/admin/login.php">Operar painel</a>
            <a class="button button-secondary" href="/eventos.php">Ver publicações</a>
        </div>
    </section>
<?php render_page_end(); ?>
