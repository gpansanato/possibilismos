<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$event = $id > 0 ? event_by_id($id) : null;

if (!$event) {
    render_page_start('Evento não encontrado', 'events', 'admin', 'O registro solicitado não foi localizado.');
    ?>
        <section class="empty">
            <h2>Evento não encontrado.</h2>
            <p>Volte para a listagem e selecione um item válido.</p>
            <p><a class="button" href="/admin/events.php">Voltar para eventos</a></p>
        </section>
    <?php
    render_page_end();
    exit;
}

$rankings = event_rankings((int) $event['id']);
$ranking = latest_event_ranking((int) $event['id']);
$enrichments = event_enrichments((int) $event['id']);
$currentPriority = $ranking ? (float) $ranking['score'] : 0.0;
$returnTo = '/admin/event-detail.php?id=' . (int) $event['id'];
$runDate = $ranking['run_date'] ?? today_key()['date'];
$currentYear = (int) substr($runDate, 0, 4);
$topics = topics_for_date($runDate);
$settings = scoring_settings();
$components = $ranking ? score_event_components($event, $topics, $currentYear, $settings) : [
    'historical' => 0.0,
    'news' => 0.0,
    'trends' => 0.0,
    'anniversary' => 0.0,
    'category' => 0.0,
    'diversity' => 0.0,
    '_details' => [],
];
if ($ranking) {
    $knownScore = (float) $components['historical'] + (float) $components['news'] + (float) $components['trends'] + (float) $components['anniversary'] + (float) $components['category'];
    $components['diversity'] = (float) $ranking['score'] - $knownScore;
}
$componentDetails = is_array($components['_details'] ?? null) ? $components['_details'] : [];
$reasons = $ranking && trim((string) $ranking['reasons']) !== ''
    ? array_values(array_filter(array_map('trim', explode(';', (string) $ranking['reasons']))))
    : [];
$matchedContexts = $ranking ? matched_contexts_for_editorial_dossier($event, $runDate) : [];
$historicalSources = array_values(array_filter($enrichments, static fn($item) => in_array($item['role'], ['canonical', 'context', 'document', 'visual', 'archive', 'museum', 'cultural', 'geographic'], true)));
$editorialTags = array_values(array_filter(array_unique(array_merge(
    [$event['category'], $event['region']],
    array_slice($componentDetails['news_keywords'] ?? [], 0, 4),
    array_slice($componentDetails['trend_keywords'] ?? [], 0, 4)
))));
$suggestedTitle = 'Por que ' . $event['title'] . ' importa no contexto de hoje';
$suggestedAngle = $ranking
    ? 'Usar o fato histórico como ponto de partida para explicar conexões possíveis com temas atuais, deixando claro que a relação é um apoio editorial e precisa de validação humana.'
    : 'Aguardar a priorização para sugerir um ângulo editorial baseado no contexto do dia.';
$riskNotes = editorial_risk_notes($event, $ranking, $enrichments, $matchedContexts, $components);

render_page_start('Dossiê editorial', 'events', 'admin', 'Evento histórico estruturado como insumo para decisão editorial.');
?>
    <section class="section-heading">
        <div>
            <p class="eyebrow"><?= h(str_pad((string) $event['event_day'], 2, '0', STR_PAD_LEFT)) ?>/<?= h(str_pad((string) $event['event_month'], 2, '0', STR_PAD_LEFT)) ?> - <?= h((string) $event['year']) ?></p>
            <h2><?= h($event['title']) ?></h2>
        </div>
        <a class="button button-secondary" href="/admin/events.php">Voltar para lista</a>
    </section>

    <section class="panel dossier-hero">
        <?php if ($event['image_url']): ?>
            <div class="event-visual">
                <img src="<?= h($event['image_url']) ?>" alt="">
            </div>
        <?php endif; ?>
        <div class="detail-grid">
            <div><span class="eyebrow">Status editorial</span><p><span class="status-badge <?= h(event_review_status_class($event['review_status'])) ?>"><?= h(event_review_status_label($event['review_status'])) ?></span></p></div>
            <div><span class="eyebrow">Prioridade atual</span><p><?= h(number_format($currentPriority, 1)) ?></p></div>
            <div><span class="eyebrow">Data de priorização</span><p><?= h($ranking['run_date'] ?? 'Sem priorização') ?></p></div>
            <div><span class="eyebrow">Categoria</span><p><?= h($event['category'] ?: 'Não informada') ?></p></div>
            <div><span class="eyebrow">Região</span><p><?= h($event['region'] ?: 'Não informada') ?></p></div>
            <div><span class="eyebrow">Origem inicial</span><p><?= h($event['canonical_source'] ?: 'Wikimedia') ?></p></div>
            <div><span class="eyebrow">ID canônico</span><p><?= h($event['canonical_id'] ?: 'Não informado') ?></p></div>
            <div><span class="eyebrow">Enriquecimento</span><p><?= $enrichments ? h(count($enrichments) . ' registros') : 'Pendente de enriquecimento' ?></p></div>
            <div><span class="eyebrow">Ativo</span><p><?= ((int) $event['active']) === 1 ? 'Sim' : 'Não' ?></p></div>
        </div>
    </section>

    <section class="dossier-grid">
        <article class="panel dossier-block">
            <span class="badge">Resumo do fato</span>
            <h1>O que aconteceu</h1>
            <p><?= h($event['description'] ?: 'Resumo ainda não disponível para uso editorial.') ?></p>
            <?php if ($event['source_url']): ?>
                <p><a class="source" href="<?= h($event['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte histórica inicial</a></p>
            <?php endif; ?>
        </article>

        <article class="panel dossier-block">
            <span class="badge">Contexto histórico</span>
            <h1>Por que o fato é relevante historicamente</h1>
            <?php if ($historicalSources): ?>
                <p><?= h(dossier_historical_context_summary($event, $historicalSources)) ?></p>
            <?php else: ?>
                <p>Pendente de enriquecimento histórico. Esta seção está preparada para receber descrição contextual, entidades, acervos e fontes complementares.</p>
            <?php endif; ?>
        </article>
    </section>

    <section class="panel dossier-block">
        <div class="section-heading">
            <div>
                <span class="badge">Conexão com o dia</span>
                <h1>Por que apareceu na priorização atual</h1>
            </div>
            <span class="status-badge <?= $ranking ? 'is-approved' : 'is-pending' ?>"><?= $ranking ? 'Priorizado' : 'Sem priorização' ?></span>
        </div>
        <p><?= h($ranking['context_summary'] ?? 'Sem contexto de priorização salvo para este evento.') ?></p>
        <p class="meta">A conexão com notícias, tendências e tópicos é um apoio editorial para investigação. Ela não afirma causalidade automática entre o fato histórico e o tema atual.</p>

        <div class="feature-grid feature-grid--three">
            <article class="feature-card">
                <h3>Notícias relacionadas</h3>
                <p><?= h((string) ((int) ($componentDetails['news_topics'] ?? 0))) ?> tópicos encontrados.</p>
                <p><?= h(implode(', ', array_slice($componentDetails['news_keywords'] ?? [], 0, 8)) ?: 'Sem palavra-chave vinculada.') ?></p>
            </article>
            <article class="feature-card">
                <h3>Tendências relacionadas</h3>
                <p><?= h((string) ((int) ($componentDetails['trend_topics'] ?? 0))) ?> tópicos encontrados.</p>
                <p><?= h(implode(', ', array_slice($componentDetails['trend_keywords'] ?? [], 0, 8)) ?: 'Sem palavra-chave vinculada.') ?></p>
            </article>
            <article class="feature-card">
                <h3>Fontes contextuais</h3>
                <p><?= h($matchedContexts ? count($matchedContexts) . ' itens vinculados por termos.' : 'Sem fonte contextual vinculada.') ?></p>
            </article>
        </div>
    </section>

    <section class="panel dossier-block">
        <span class="badge">Score explicável</span>
        <h1>Decomposição da prioridade</h1>
        <div class="score-breakdown">
            <?php foreach ([
                'historical' => ['Relevância histórica', 'Peso aplicado sobre o score base do evento.'],
                'news' => ['Notícias relacionadas', 'Pontos por tópicos e palavras-chave de notícias conectadas.'],
                'trends' => ['Tendências relacionadas', 'Pontos por tópicos e palavras-chave de tendências conectadas.'],
                'anniversary' => ['Bônus de aniversário', 'Marco temporal relevante para a data avaliada.'],
                'category' => ['Categoria em pauta', 'Categoria do fato apareceu no contexto coletado.'],
                'diversity' => ['Diversidade/penalização', 'Ajuste para evitar concentração excessiva de temas.'],
            ] as $key => $definition): ?>
                <div class="score-card">
                    <strong><?= h($definition[0]) ?></strong>
                    <span><?= h(number_format((float) ($components[$key] ?? 0), 1)) ?></span>
                    <p><?= h($definition[1]) ?></p>
                </div>
            <?php endforeach; ?>
            <div class="score-card is-total">
                <strong>Score final</strong>
                <span><?= h(number_format($currentPriority, 1)) ?></span>
                <p>Prioridade salva para decisão editorial.</p>
            </div>
        </div>
        <?php if ($reasons): ?>
            <ul class="clean-list">
                <?php foreach ($reasons as $reason): ?>
                    <li><?= h($reason) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>Nenhuma justificativa textual salva. Aplique a priorização para gerar os motivos do score.</p>
        <?php endif; ?>
    </section>

    <section class="dossier-grid">
        <article class="panel dossier-block">
            <span class="badge">Ângulo editorial sugerido</span>
            <h1>Como pode virar pauta</h1>
            <div class="detail-grid detail-grid--two">
                <div><span class="eyebrow">Título possível</span><p><?= h($suggestedTitle) ?></p></div>
                <div><span class="eyebrow">Formatos</span><p>Post, newsletter, artigo curto, carrossel, roteiro, aula ou nota editorial.</p></div>
            </div>
            <p><?= h($suggestedAngle) ?></p>
            <div class="meta">
                <?php foreach ($editorialTags as $tag): ?>
                    <span><?= h($tag) ?></span>
                <?php endforeach; ?>
                <?php if (!$editorialTags): ?><span>Tags pendentes</span><?php endif; ?>
            </div>
        </article>

        <article class="panel dossier-block">
            <span class="badge">Riscos e observações</span>
            <h1>Cuidados editoriais</h1>
            <ul class="clean-list">
                <?php foreach ($riskNotes as $note): ?>
                    <li><?= h($note) ?></li>
                <?php endforeach; ?>
            </ul>
        </article>
    </section>

    <section class="panel dossier-block">
        <span class="badge">Fontes e rastreabilidade</span>
        <h1>Base usada no dossiê</h1>
        <div class="source-columns">
            <div>
                <h2>Fontes históricas</h2>
                <?php if (!$historicalSources): ?>
                    <p>Pendente de enriquecimento histórico.</p>
                <?php else: ?>
                    <?php foreach ($historicalSources as $item): ?>
                        <article class="source-item">
                            <strong><?= h($item['source']) ?></strong>
                            <p><?= h($item['title'] ?: $item['role']) ?></p>
                            <?php if ($item['description']): ?><p><?= h($item['description']) ?></p><?php endif; ?>
                            <?php if ($item['source_url']): ?><a href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div>
                <h2>Fontes contextuais</h2>
                <?php if (!$matchedContexts): ?>
                    <p>Sem fonte contextual vinculada por termo nesta priorização.</p>
                <?php else: ?>
                    <?php foreach (array_slice($matchedContexts, 0, 8) as $context): ?>
                        <article class="source-item">
                            <strong><?= h($context['context_type'] === 'news' ? 'Notícia' : 'Tendência') ?>: <?= h($context['source']) ?></strong>
                            <p><?= h($context['title']) ?></p>
                            <p><?= h($context['keywords']) ?></p>
                            <?php if ($context['source_url']): ?><a href="<?= h($context['source_url']) ?>" target="_blank" rel="noopener">Abrir contexto</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel">
        <h1>Ações editoriais</h1>
        <div class="actions">
            <form class="actions actions-inline" method="post" action="/admin/update-event-status.php">
                <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                <button name="review_status" value="approved" type="submit">Aprovar evento</button>
                <button name="review_status" value="pending" type="submit">Marcar como pendente</button>
                <button class="danger" name="review_status" value="rejected" type="submit">Reprovar evento</button>
            </form>
            <?php if ($ranking): ?>
                <form class="actions actions-inline" method="post" action="/admin/update-ranking.php">
                    <input type="hidden" name="id" value="<?= h((string) $ranking['id']) ?>">
                    <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                    <button name="status" value="approved" type="submit">Publicar priorização</button>
                    <button name="status" value="suggested" type="submit">Manter sugerida</button>
                    <button class="danger" name="status" value="rejected" type="submit">Não publicar</button>
                </form>
            <?php endif; ?>
            <form class="actions actions-inline" method="post" action="/admin/enrich-event.php">
                <input type="hidden" name="id" value="<?= h((string) $event['id']) ?>">
                <input type="hidden" name="return_to" value="<?= h($returnTo) ?>">
                <button class="button-secondary" type="submit">Enriquecer novamente</button>
            </form>
            <a class="button button-secondary" href="/admin/events.php">Voltar para lista</a>
        </div>
    </section>

    <section class="panel">
        <h1>Histórico de priorização</h1>
        <?php if (!$rankings): ?>
            <p>Nenhum score diário salvo para este evento.</p>
        <?php else: ?>
            <div class="table-wrap table-wrap--plain">
                <table class="data-table">
                    <thead><tr><th>Data</th><th>Score</th><th>Status ranking</th><th>Resumo</th></tr></thead>
                    <tbody>
                        <?php foreach ($rankings as $item): ?>
                            <tr>
                                <td data-label="Data"><?= h($item['run_date']) ?></td>
                                <td data-label="Score"><?= h(number_format((float) $item['score'], 1)) ?></td>
                                <td data-label="Status"><?= h($item['status']) ?></td>
                                <td data-label="Resumo"><?= h($item['context_summary']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php render_page_end(); ?>

<?php
function matched_contexts_for_editorial_dossier(array $event, string $runDate): array
{
    $eventText = normalize_score_text($event['title'] . ' ' . $event['description'] . ' ' . $event['category'] . ' ' . $event['region']);
    $matches = [];

    foreach (collected_contexts_for_date($runDate) as $context) {
        $keywords = preg_split('/\s+/u', normalize_score_text($context['keywords'] ?? ''));
        foreach (array_filter(array_unique($keywords)) as $keyword) {
            if (mb_strlen($keyword, 'UTF-8') >= 4 && mb_strpos($eventText, $keyword, 0, 'UTF-8') !== false) {
                $matches[] = $context;
                break;
            }
        }
    }

    return $matches;
}

function dossier_historical_context_summary(array $event, array $sources): string
{
    $sourceNames = array_values(array_unique(array_map(static fn($item) => $item['source'], $sources)));
    $parts = [];
    $parts[] = 'O evento está associado a ' . ($event['category'] ?: 'uma categoria histórica ainda não classificada') . ' e ocorreu em ' . $event['year'] . '.';
    $parts[] = 'A base possui ' . count($sources) . ' registro(s) de enriquecimento de ' . implode(', ', array_slice($sourceNames, 0, 4)) . '.';
    $parts[] = 'Use estes materiais para validar relevância, personagens, localidade e formulação editorial antes da publicação.';

    return implode(' ', $parts);
}

function editorial_risk_notes(array $event, ?array $ranking, array $enrichments, array $matchedContexts, array $components): array
{
    $notes = [];
    if (!$ranking) {
        $notes[] = 'Evento ainda não priorizado: falta conexão calculada com o contexto do dia.';
    }
    if (!$enrichments) {
        $notes[] = 'Pendente de enriquecimento: validar fonte histórica antes de transformar em publicação.';
    }
    if (!$matchedContexts) {
        $notes[] = 'Sem fonte contextual vinculada: a conexão com o dia pode estar fraca ou depender de revisão manual.';
    }
    if (((float) ($components['news'] ?? 0) + (float) ($components['trends'] ?? 0)) <= 0 && $ranking) {
        $notes[] = 'Prioridade sem pontos de notícias ou tendências: verificar se o gancho editorial depende apenas de data, categoria ou curadoria humana.';
    }
    if (preg_match('/guerra|morte|ataque|golpe|ditadura|relig/i', $event['title'] . ' ' . $event['description'] . ' ' . $event['category'])) {
        $notes[] = 'Tema potencialmente sensível: evitar simplificação e conferir linguagem, contexto e fontes.';
    }
    if (!$event['source_url'] && !$event['canonical_id']) {
        $notes[] = 'Fonte inicial incompleta: registrar referência canônica ou fonte histórica verificável.';
    }

    $notes[] = 'A relação com o contexto atual deve ser tratada como hipótese editorial, não como afirmação automática de causalidade.';

    return array_values(array_unique($notes));
}
