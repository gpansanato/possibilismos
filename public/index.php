<?php
require_once __DIR__ . '/../app/bootstrap.php';

$today = today_key();
$items = published_rankings_for_date($today['date']);
render_page_start('Possibilismos', 'home', 'public', null, false);
?>
    <section class="hero">
        <div class="hero__content">
            <?php component_badge('Plataforma de curadoria historica'); ?>
            <h1>Fatos historicos priorizados pelo contexto do dia.</h1>
            <p>Coleta eventos, noticias e tendencias para apoiar uma curadoria diaria clara, auditavel e pronta para publicacao.</p>
            <div class="hero__actions">
                <a class="button button-primary" href="#fatos-de-hoje">Ver fatos publicados</a>
                <a class="button button-secondary" href="/admin/login.php">Acessar operacao</a>
            </div>
        </div>

        <aside class="mock-window" aria-label="Mockup do painel de priorizacao">
            <div class="mock-window__bar">
                <span></span><span></span><span></span>
                <strong>Prioridade diaria</strong>
            </div>
            <div class="mock-grid">
                <?php component_metric('Eventos', '128'); ?>
                <?php component_metric('Noticias', '42'); ?>
                <?php component_metric('Tendencias', '18'); ?>
            </div>
            <?php component_mock_row('Terremoto de Valdivia', 'Score 84.5 - ciencia', 'Aprovado'); ?>
            <?php component_mock_row('Pacto de Aco', 'Score 72.0 - politica', 'Revisar'); ?>
            <?php component_mock_row('Arthur Conan Doyle', 'Score 64.0 - cultura', 'Pendente'); ?>
            <div class="mock-bars">
                <span style="width: 84%"></span>
                <span style="width: 68%"></span>
                <span style="width: 52%"></span>
            </div>
        </aside>
    </section>

    <section class="landing-section">
        <div class="section-copy">
            <?php component_badge('Uso'); ?>
            <h2>Para quem precisa transformar contexto em pauta.</h2>
            <p>O produto ajuda operacoes editoriais, pesquisadores e gestores de conteudo a enxergar relacoes entre historia, noticias e temas em alta.</p>
        </div>
        <div class="feature-grid feature-grid--three">
            <article class="feature-card">
                <span class="feature-icon">01</span>
                <h3>Curadoria editorial</h3>
                <p>Organize fatos relevantes do dia com status, score e justificativa de priorizacao.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">02</span>
                <h3>Operacao recorrente</h3>
                <p>Execute coletas diarias, revise pendencias e publique apenas o que passou pela avaliacao.</p>
            </article>
            <article class="feature-card">
                <span class="feature-icon">03</span>
                <h3>Analise de contexto</h3>
                <p>Compare eventos historicos com noticias e tendencias para encontrar conexoes atuais.</p>
            </article>
        </div>
    </section>

    <section class="landing-section">
        <div class="section-copy">
            <?php component_badge('Beneficios'); ?>
            <h2>Um fluxo claro do dado bruto ate a publicacao.</h2>
        </div>
        <div class="feature-grid">
            <article class="feature-card"><span class="feature-icon">A</span><h3>Coleta estruturada</h3><p>Eventos historicos, noticias e tendencias entram por etapas separadas.</p></article>
            <article class="feature-card"><span class="feature-icon">B</span><h3>Base higienizada</h3><p>Noticias e tendencias sao persistidas com normalizacao e deduplicacao.</p></article>
            <article class="feature-card"><span class="feature-icon">C</span><h3>Score explicavel</h3><p>Cada prioridade mostra componentes como noticias, tendencias e aniversario.</p></article>
            <article class="feature-card"><span class="feature-icon">D</span><h3>Estados editoriais</h3><p>Eventos podem ficar pendentes, aprovados ou reprovados antes do ranking.</p></article>
            <article class="feature-card"><span class="feature-icon">E</span><h3>Parametros ajustaveis</h3><p>Pesos do score podem ser calibrados no painel sem alterar codigo.</p></article>
            <article class="feature-card"><span class="feature-icon">F</span><h3>Publicacao controlada</h3><p>A area publica exibe apenas fatos aprovados e consistentes.</p></article>
        </div>
    </section>

    <section class="product-section">
        <div class="section-copy">
            <?php component_badge('Produto em acao'); ?>
            <h2>Painel operacional para revisar e decidir.</h2>
            <p>O mockup abaixo simula uma rotina de curadoria: eventos coletados, status editorial, contexto disponivel e score de prioridade.</p>
        </div>
        <div class="dashboard-mockup">
            <div class="mock-window__bar">
                <span></span><span></span><span></span>
                <strong>Operacao diaria</strong>
            </div>
            <div class="dashboard-mockup__body">
                <aside>
                    <b>Coletas</b>
                    <small>Eventos historicos</small>
                    <small>Noticias do dia</small>
                    <small>Tendencias</small>
                    <small>Score</small>
                </aside>
                <div>
                    <?php component_mock_row('Evento aprovado', 'noticias + tendencias + aniversario', 'Score 91'); ?>
                    <?php component_mock_row('Evento pendente', 'aguardando curadoria', 'Pendente'); ?>
                    <?php component_mock_row('Contexto higienizado', 'rss: Google News - trend: Google Trends', 'Atualizado'); ?>
                    <?php component_mock_row('Publicacao diaria', '5 fatos selecionados', 'Pronto'); ?>
                </div>
            </div>
        </div>
    </section>

    <section class="split-section">
        <div class="mobile-mockup" aria-label="Mockup mobile">
            <div class="mobile-mockup__screen">
                <span class="badge">Hoje</span>
                <h3>5 fatos</h3>
                <p>Score, status e contexto em uma visualizacao compacta.</p>
                <div class="mini-list"></div>
                <div class="mini-list is-short"></div>
                <div class="mini-list"></div>
            </div>
        </div>
        <div class="section-copy">
            <?php component_badge('Responsivo'); ?>
            <h2>Consulta rapida em qualquer tela.</h2>
            <ul class="clean-list">
                <li>Acesso direto ao painel administrativo.</li>
                <li>Cards empilhados em mobile, sem overflow horizontal.</li>
                <li>Controles confortaveis para rotinas recorrentes.</li>
                <li>Hierarquia visual consistente entre publico e admin.</li>
            </ul>
        </div>
    </section>

    <section class="landing-section">
        <div class="section-copy">
            <?php component_badge('Adocao'); ?>
            <h2>Comece simples e evolua com controle.</h2>
        </div>
        <div class="feature-grid feature-grid--three">
            <article class="feature-card"><h3>Configurar fontes</h3><p>Use as fontes padrao ou ajuste RSS e tendencias conforme a operacao.</p></article>
            <article class="feature-card"><h3>Revisar pendencias</h3><p>Eventos entram como nao avaliados para evitar publicacao acidental.</p></article>
            <article class="feature-card"><h3>Calibrar score</h3><p>Parametros permitem mudar pesos sem refatorar a aplicacao.</p></article>
        </div>
    </section>

    <section class="comparison-section">
        <div class="section-copy">
            <?php component_badge('Implantacao'); ?>
            <h2>Modelos de uso para diferentes maturidades.</h2>
        </div>
        <div class="feature-grid feature-grid--three">
            <article class="offer-card"><h3>MVP hospedado</h3><p>PHP + MySQL com operacao manual assistida.</p><strong>Validacao rapida</strong></article>
            <article class="offer-card is-featured"><h3>Operacao editorial</h3><p>Rotina diaria, revisao, score e publicacao controlada.</p><strong>Uso recomendado</strong></article>
            <article class="offer-card"><h3>Escala futura</h3><p>Fila de coletas, IA externa, logs e automacoes avancadas.</p><strong>Evolucao</strong></article>
        </div>
    </section>

    <section id="fatos-de-hoje" class="landing-section">
        <div class="section-copy">
            <?php component_badge($today['date']); ?>
            <h2>Fatos aprovados de hoje.</h2>
        </div>

        <?php if (!$items): ?>
            <section class="empty">
                <h2>Nenhum fato aprovado para hoje.</h2>
                <p>A operacao pode coletar contexto, aplicar score e aprovar os fatos no painel administrativo.</p>
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
    </section>

    <section class="final-cta">
        <div>
            <?php component_badge('Proximo ciclo'); ?>
            <h2>Transforme a curadoria diaria em um processo claro.</h2>
            <p>Execute coletas, revise fatos, ajuste o score e publique apenas o que faz sentido para o contexto atual.</p>
        </div>
        <div class="hero__actions">
            <a class="button button-primary" href="/admin/login.php">Operar painel</a>
            <a class="button button-secondary" href="#fatos-de-hoje">Ver publicacoes</a>
        </div>
    </section>
<?php render_page_end(); ?>
