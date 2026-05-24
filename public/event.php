<?php
require_once __DIR__ . '/../app/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$item = $id > 0 ? public_ranking_by_id($id) : null;

if (!$item) {
    render_page_start('Dossiê não encontrado', 'events', 'public', 'O evento solicitado não está disponível para visualização pública.');
    ?>
        <section class="empty">
            <h2>Dossiê não disponível.</h2>
            <p>O evento pode ainda não ter sido aprovado, priorizado ou liberado para publicação.</p>
            <p><a class="button" href="/eventos.php">Voltar para eventos publicados</a></p>
        </section>
    <?php
    render_page_end();
    exit;
}

$event = [
    'id' => (int) $item['event_id'],
    'title' => $item['title'],
    'description' => $item['description'],
    'category' => $item['category'],
    'region' => $item['region'],
    'year' => $item['year'],
    'base_score' => $item['base_score'],
];
$enrichments = event_enrichments((int) $item['event_id']);
$historicalSources = array_values(array_filter($enrichments, static fn($source) => in_array($source['role'], ['canonical', 'context', 'document', 'visual', 'archive', 'museum', 'cultural', 'geographic'], true)));
$matchedContexts = public_dossier_contexts($event, (string) $item['run_date']);
$priorityLabel = public_priority_label((float) $item['score']);
$dateLabel = str_pad((string) $item['event_day'], 2, '0', STR_PAD_LEFT) . '/' . str_pad((string) $item['event_month'], 2, '0', STR_PAD_LEFT);
$editorialReason = public_editorial_reason($item, count($matchedContexts));
$suggestedAngle = 'Este evento pode ser lido hoje a partir das conexões editoriais identificadas na priorização, sempre como hipótese de pauta e não como relação causal automática.';

render_page_start('Dossiê editorial', 'events', 'public', null, false);
?>
    <article class="public-dossier">
        <header class="dossier-header">
            <div>
                <?php component_badge('Dossiê editorial público'); ?>
                <h1><?= h($item['title']) ?></h1>
                <p><?= h($dateLabel) ?> · <?= h((string) $item['year']) ?> · <?= h(public_display_label($item['category'], 'Categoria em validação')) ?></p>
            </div>
            <div class="dossier-header__actions">
                <span class="status-badge is-approved">Prioridade <?= h($priorityLabel) ?></span>
                <a class="button button-secondary" href="/eventos.php?date=<?= h($item['run_date']) ?>">Voltar para publicações</a>
            </div>
        </header>

        <section class="panel dossier-hero">
            <?php if ($item['image_url']): ?>
                <div class="event-visual event-visual--dossier">
                    <img src="<?= h($item['image_url']) ?>" alt="">
                </div>
            <?php endif; ?>
            <div class="detail-grid detail-grid--dossier">
                <div><span class="eyebrow">Prioridade editorial</span><p><?= h($priorityLabel) ?></p></div>
                <div><span class="eyebrow">Categoria</span><p><?= h(public_display_label($item['category'], 'Não informada')) ?></p></div>
                <div><span class="eyebrow">Região ou entidade</span><p><?= h(public_display_label($item['region'], 'Não informada')) ?></p></div>
                <div><span class="eyebrow">Data histórica</span><p><?= h($dateLabel) ?></p></div>
                <div><span class="eyebrow">Ano</span><p><?= h((string) $item['year']) ?></p></div>
                <div><span class="eyebrow">Fonte inicial</span><p><?= h($item['canonical_source'] ?: 'Em validação') ?></p></div>
            </div>
        </section>

        <section class="dossier-grid">
            <article class="panel dossier-block">
                <span class="badge">Resumo do fato</span>
                <h2>O que aconteceu</h2>
                <p><?= h($item['description'] ?: 'Resumo editorial em validação.') ?></p>
            </article>

            <article class="panel dossier-block">
                <span class="badge">Contexto histórico</span>
                <h2>Por que é relevante</h2>
                <?php if ($historicalSources): ?>
                    <p><?= h(public_historical_context_summary($item, $historicalSources)) ?></p>
                <?php else: ?>
                    <p>Contexto histórico complementar em validação. A fonte inicial deve ser consultada antes de uso editorial aprofundado.</p>
                <?php endif; ?>
            </article>
        </section>

        <section class="panel dossier-block dossier-block--highlight">
            <span class="badge">Destaque editorial</span>
            <h2>Por que este evento foi destacado</h2>
            <p><?= h($editorialReason) ?></p>
            <p class="meta">As conexões são apoio editorial para leitura do dia. Elas não indicam causalidade automática entre o fato histórico e os temas atuais.</p>
        </section>

        <section class="dossier-grid">
            <article class="panel dossier-block">
                <span class="badge">Conexões com o dia</span>
                <h2>Temas associados</h2>
                <?php if (!$matchedContexts): ?>
                    <p>Sem fonte contextual pública vinculada por termo nesta priorização.</p>
                <?php else: ?>
                    <?php foreach (array_slice($matchedContexts, 0, 5) as $context): ?>
                        <article class="source-item">
                            <strong><?= h($context['context_type'] === 'news' ? 'Notícia' : 'Tendência') ?></strong>
                            <p><?= h($context['title']) ?></p>
                            <?php if (trim((string) $context['keywords']) !== ''): ?>
                                <p class="meta"><?= h($context['keywords']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>

            <article class="panel dossier-block">
                <span class="badge">Leitura sugerida</span>
                <h2>Como usar este fato</h2>
                <p><?= h($suggestedAngle) ?></p>
                <div class="meta">
                    <?php foreach (array_filter([public_display_label($item['category']), public_display_label($item['region'], ''), 'Prioridade ' . $priorityLabel]) as $tag): ?>
                        <span><?= h($tag) ?></span>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="panel dossier-block">
            <span class="badge">Fontes</span>
            <h2>Rastreabilidade pública</h2>
            <div class="source-columns">
                <div>
                    <h3>Fontes históricas</h3>
                    <?php if (!$historicalSources && !$item['source_url']): ?>
                        <p>Fonte em validação.</p>
                    <?php endif; ?>
                    <?php if ($item['source_url']): ?>
                        <article class="source-item">
                            <strong>Fonte inicial</strong>
                            <p><?= h($item['canonical_source'] ?: 'Referência histórica') ?></p>
                            <a href="<?= h($item['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte</a>
                        </article>
                    <?php endif; ?>
                    <?php foreach (array_slice($historicalSources, 0, 6) as $source): ?>
                        <article class="source-item">
                            <strong><?= h($source['source']) ?></strong>
                            <p><?= h($source['title'] ?: $source['role']) ?></p>
                            <?php if ($source['source_url']): ?><a href="<?= h($source['source_url']) ?>" target="_blank" rel="noopener">Abrir fonte</a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div>
                    <h3>Fontes contextuais</h3>
                    <?php if (!$matchedContexts): ?>
                        <p>Sem fonte contextual pública vinculada.</p>
                    <?php else: ?>
                        <?php foreach (array_slice($matchedContexts, 0, 6) as $context): ?>
                            <article class="source-item">
                                <strong><?= h($context['source']) ?></strong>
                                <p><?= h($context['title']) ?></p>
                                <?php if ($context['source_url']): ?><a href="<?= h($context['source_url']) ?>" target="_blank" rel="noopener">Abrir contexto</a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </article>
<?php render_page_end(); ?>

<?php
function public_dossier_contexts(array $event, string $runDate): array
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

function public_historical_context_summary(array $item, array $sources): string
{
    $sourceNames = array_values(array_unique(array_map(static fn($source) => $source['source'], $sources)));
    $category = public_display_label($item['category'] ?? null, 'histórica');

    return 'O fato pertence à categoria ' . mb_strtolower($category, 'UTF-8') . ' e possui apoio de ' . count($sources) . ' registro(s) de enriquecimento, incluindo ' . implode(', ', array_slice($sourceNames, 0, 3)) . '.';
}
