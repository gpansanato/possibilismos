<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$today = today_key();
$date = $_GET['date'] ?? $today['date'];
if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $date = $today['date'];
}

$message = null;
$error = null;
$config = require __DIR__ . '/../app/config.php';
$sourceConfig = $config['sources'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $actionDate = $_POST['date'] ?? $date;
    if (!is_string($actionDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $actionDate)) {
        $actionDate = $today['date'];
    }
    $date = $actionDate;
    $parts = date_parts_from_run_date($actionDate);

    try {
        if ($action === 'collect_events') {
            $result = collect_historical_events_for_day($parts['month'], $parts['day'], $actionDate);
            $message = $result['imported'] . ' eventos coletados e ' . $result['enriched'] . ' enriquecimentos salvos.';
        } elseif ($action === 'enrich_events') {
            $ids = source_event_ids_for_day($parts['month'], $parts['day']);
            $enriched = 0;
            foreach ($ids as $eventId) {
                $enriched += enrich_historical_event($eventId);
            }
            $message = count($ids) . ' eventos processados para enriquecimento; ' . $enriched . ' registros salvos/atualizados.';
        } elseif ($action === 'collect_news') {
            $items = collect_daily_news_topics($actionDate);
            $message = count($items) . ' notícias persistidas/coletadas.';
        } elseif ($action === 'collect_trends') {
            $items = collect_daily_trend_topics($actionDate);
            $message = count($items) . ' tendências persistidas/coletadas.';
        } elseif ($action === 'collect_context') {
            $news = collect_daily_news_topics($actionDate);
            $trends = collect_daily_trend_topics($actionDate);
            $message = count($news) . ' notícias e ' . count($trends) . ' tendências persistidas/coletadas.';
        } elseif ($action === 'prioritize_events') {
            $ranked = apply_daily_priority_score($actionDate);
            $message = count($ranked) . ' eventos priorizados para ' . $actionDate . '.';
        } elseif ($action === 'full_pipeline') {
            $events = collect_historical_events_for_day($parts['month'], $parts['day'], $actionDate);
            $news = collect_daily_news_topics($actionDate);
            $trends = collect_daily_trend_topics($actionDate);
            $ranked = apply_daily_priority_score($actionDate);
            $message = 'Fluxo completo executado: ' . $events['imported'] . ' eventos, ' . count($news) . ' notícias, ' . count($trends) . ' tendências e ' . count($ranked) . ' priorizações.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$parts = date_parts_from_run_date($date);
$eventCounts = events_count_by_review_status($parts['month'], $parts['day']);
$historicalSummary = historical_collection_summary_for_day($parts['month'], $parts['day']);
$importSummary = event_import_summary_for_date($date);
$newsCount = collected_contexts_count_for_date($date, 'news');
$trendCount = collected_contexts_count_for_date($date, 'trend');
$topicsCount = current_topics_count_for_date($date);
$rankingCount = rankings_count_for_date($date);
$sources = source_catalog($sourceConfig);

render_page_start('Fontes e coletas', 'sources', 'admin', 'Central de configuração operacional das fontes, coletas e processamentos por data.');
?>
    <?php if ($error): ?><section class="empty"><p><?= h($error) ?></p></section><?php endif; ?>
    <?php if ($message): ?><section class="panel"><p><?= h($message) ?></p></section><?php endif; ?>

    <section class="panel">
        <form class="filter-form" method="get">
            <label>Data de referência <input type="date" name="date" value="<?= h($date) ?>"></label>
            <button type="submit">Ver data</button>
            <a class="button button-secondary" href="/admin/sources.php">Hoje</a>
        </form>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Modelo de coleta</p>
                <h2>Executar processamento</h2>
                <p>Escolha a etapa operacional para a data selecionada. As telas de eventos, contexto e priorização ficam reservadas para consulta, curadoria e análise.</p>
            </div>
        </div>
        <form class="actions" method="post">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <button name="action" value="collect_events" type="submit">Coletar eventos históricos</button>
            <button class="button-secondary" name="action" value="enrich_events" type="submit">Enriquecer eventos</button>
            <button class="button-secondary" name="action" value="collect_news" type="submit">Coletar notícias</button>
            <button class="button-secondary" name="action" value="collect_trends" type="submit">Coletar tendências</button>
            <button class="button-secondary" name="action" value="collect_context" type="submit">Coletar contexto</button>
            <button class="button-secondary" name="action" value="prioritize_events" type="submit">Priorizar eventos</button>
            <button class="button-secondary" name="action" value="full_pipeline" type="submit">Executar fluxo completo</button>
        </form>
    </section>

    <section class="feature-grid feature-grid--three">
        <article class="feature-card">
            <span class="badge">Eventos</span>
            <h3><?= h((string) $historicalSummary['total']) ?> fatos históricos</h3>
            <p><?= h($eventCounts['pending'] . ' não publicados, ' . $eventCounts['approved'] . ' publicados, ' . $eventCounts['rejected'] . ' reprovados') ?></p>
        </article>
        <article class="feature-card">
            <span class="badge">Contexto</span>
            <h3><?= h((string) ($newsCount + $trendCount)) ?> itens higienizados</h3>
            <p><?= h($newsCount . ' notícias, ' . $trendCount . ' tendências e ' . $topicsCount . ' tópicos operacionais') ?></p>
        </article>
        <article class="feature-card">
            <span class="badge">Priorização</span>
            <h3><?= h((string) $rankingCount) ?> rankings</h3>
            <p><?= h($importSummary['linked'] . ' imports vinculados e ' . $historicalSummary['enrichment_records'] . ' enriquecimentos salvos') ?></p>
        </article>
    </section>

    <section class="panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Fontes externas</p>
                <h2>Catálogo operacional</h2>
            </div>
        </div>
        <div class="table-wrap table-wrap--plain">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fonte</th>
                        <th>Entidade</th>
                        <th>Status</th>
                        <th>Configuração</th>
                        <th>Dados obtidos</th>
                        <th>Processo</th>
                        <th>Destino</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sources as $source): ?>
                        <tr>
                            <td data-label="Fonte"><strong><?= h($source['name']) ?></strong><small><?= h($source['key']) ?></small></td>
                            <td data-label="Entidade"><?= h($source['entity']) ?></td>
                            <td data-label="Status"><span class="status-badge <?= h(source_status_class($source['status'])) ?>"><?= h($source['status']) ?></span></td>
                            <td data-label="Configuração">
                                <div class="source-checks">
                                    <?php foreach ($source['checks'] as $check): ?>
                                        <span class="status-badge <?= $check['ok'] ? 'is-approved' : 'is-pending' ?>"><?= h($check['label']) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td data-label="Dados"><?= h($source['data']) ?></td>
                            <td data-label="Processo"><?= h($source['process']) ?></td>
                            <td data-label="Destino"><?= h($source['target']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php render_page_end(); ?>

<?php
function source_event_ids_for_day(int $month, int $day): array
{
    $stmt = db()->prepare('SELECT id FROM events WHERE event_month = ? AND event_day = ?');
    $stmt->execute([$month, $day]);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function source_status_class(string $status): string
{
    return match ($status) {
        'Ativa' => 'is-approved',
        'Aguardando credencial', 'Aguardando endpoint' => 'is-pending',
        default => 'is-rejected',
    };
}

function source_status(bool $enabled, bool $configured, string $missingLabel = 'credencial'): string
{
    if ($enabled && $configured) {
        return 'Ativa';
    }

    if ($enabled && !$configured) {
        return $missingLabel === 'endpoint' ? 'Aguardando endpoint' : 'Aguardando credencial';
    }

    return 'Inativa';
}

function source_checks(bool $enabled, bool $credentialRequired, bool $hasCredential, bool $endpointRequired, bool $hasEndpoint): array
{
    $checks = [
        ['label' => $enabled ? 'habilitada' : 'desativada', 'ok' => $enabled],
    ];

    if ($credentialRequired) {
        $checks[] = ['label' => $hasCredential ? 'credencial ok' : 'sem credencial', 'ok' => $hasCredential];
    } else {
        $checks[] = ['label' => 'sem credencial', 'ok' => true];
    }

    if ($endpointRequired) {
        $checks[] = ['label' => $hasEndpoint ? 'endpoint ok' : 'sem endpoint', 'ok' => $hasEndpoint];
    } else {
        $checks[] = ['label' => 'endpoint padrão', 'ok' => true];
    }

    $checks[] = ['label' => ($enabled && (!$credentialRequired || $hasCredential) && (!$endpointRequired || $hasEndpoint)) ? 'pronta' : 'pendente', 'ok' => $enabled && (!$credentialRequired || $hasCredential) && (!$endpointRequired || $hasEndpoint)];

    return $checks;
}

function source_catalog(array $config): array
{
    $historical = $config['historical'] ?? [];
    $wikimedia = $config['wikimedia'] ?? [];
    $news = $config['news'] ?? [];
    $trends = $config['trends'] ?? [];
    $europeana = $historical['europeana'] ?? [];
    $smithsonian = $historical['smithsonian'] ?? [];
    $dpla = $historical['dpla'] ?? [];
    $openHistoricalMap = $historical['openhistoricalmap'] ?? [];
    $mediaCloud = $trends['media_cloud'] ?? [];
    $wikidataEnabled = !empty($historical['enabled']) && !empty($historical['wikidata']['enabled']);
    $wikimediaEnabled = !empty($wikimedia['enabled']);
    $wikipediaEnabled = !empty($historical['wikipedia']['enabled']);
    $commonsEnabled = !empty($historical['commons']['enabled']);
    $locEnabled = !empty($historical['library_of_congress']['enabled']);
    $europeanaEnabled = !empty($europeana['enabled']);
    $smithsonianEnabled = !empty($smithsonian['enabled']);
    $dplaEnabled = !empty($dpla['enabled']);
    $ohmEnabled = !empty($openHistoricalMap['enabled']);
    $newsEnabled = !empty($news['enabled']);
    $gdeltEnabled = !empty($trends['enabled']) && !empty($trends['gdelt']['enabled']);
    $mediaCloudEnabled = !empty($mediaCloud['enabled']);
    $pageviewsEnabled = !empty($trends['enabled']) && !empty($trends['wikimedia_pageviews']['enabled']);
    $agenciaEnabled = !empty($trends['enabled']) && !empty($trends['agencia_brasil']['enabled']);
    $hackerNewsEnabled = !empty($trends['enabled']) && !empty($trends['hacker_news']['enabled']);

    return [
        [
            'key' => 'historical.wikidata',
            'name' => 'Wikidata',
            'entity' => 'Evento histórico',
            'status' => source_status($wikidataEnabled, true),
            'checks' => source_checks($wikidataEnabled, false, true, false, true),
            'data' => 'ID canônico, data, ano, tipo, local e artigo associado.',
            'process' => 'Consulta SPARQL por mês/dia e normalização canônica.',
            'target' => 'events, event_imports, event_sources, event_enrichments',
        ],
        [
            'key' => 'wikimedia.onthisday',
            'name' => 'Wikipedia / Wikimedia On This Day',
            'entity' => 'Evento histórico',
            'status' => source_status($wikimediaEnabled, true),
            'checks' => source_checks($wikimediaEnabled, false, true, false, true),
            'data' => 'Ano, descrição, página relacionada, idioma e tipo de efeméride.',
            'process' => 'Fallback quando Wikidata não retorna eventos.',
            'target' => 'events, event_imports, event_sources, event_enrichments',
        ],
        [
            'key' => 'historical.wikipedia',
            'name' => 'Wikipedia REST Summary',
            'entity' => 'Enriquecimento contextual',
            'status' => source_status($wikipediaEnabled, true),
            'checks' => source_checks($wikipediaEnabled, false, true, false, true),
            'data' => 'Resumo, URL canônica, thumbnail e metadados da página.',
            'process' => 'Enriquecimento a partir do artigo associado ao evento.',
            'target' => 'events.image_url, event_enrichments',
        ],
        [
            'key' => 'historical.commons',
            'name' => 'Wikimedia Commons',
            'entity' => 'Enriquecimento visual',
            'status' => source_status($commonsEnabled, true),
            'checks' => source_checks($commonsEnabled, false, true, false, true),
            'data' => 'Imagem associada ao resumo Wikimedia e licença indicada.',
            'process' => 'Registro visual derivado do thumbnail da Wikipedia.',
            'target' => 'event_enrichments',
        ],
        [
            'key' => 'historical.library_of_congress',
            'name' => 'Library of Congress',
            'entity' => 'Enriquecimento documental',
            'status' => source_status($locEnabled, true),
            'checks' => source_checks($locEnabled, false, true, false, true),
            'data' => 'Título, descrição/data, URL, imagem e direitos.',
            'process' => 'Busca por termo do evento e uso do primeiro resultado.',
            'target' => 'event_enrichments',
        ],
        [
            'key' => 'historical.europeana',
            'name' => 'Europeana',
            'entity' => 'Enriquecimento cultural',
            'status' => source_status($europeanaEnabled, !empty($europeana['api_key'])),
            'checks' => source_checks($europeanaEnabled, true, !empty($europeana['api_key']), false, true),
            'data' => 'Obras, objetos, descrição, preview, direitos e identificador.',
            'process' => 'Busca cultural com parser específico para items, preview, direitos e identificador.',
            'target' => 'event_enrichments',
        ],
        [
            'key' => 'historical.smithsonian',
            'name' => 'Smithsonian Open Access',
            'entity' => 'Enriquecimento museológico',
            'status' => source_status($smithsonianEnabled, !empty($smithsonian['api_key'])),
            'checks' => source_checks($smithsonianEnabled, true, !empty($smithsonian['api_key']), false, true),
            'data' => 'Objetos, títulos, descrições, imagens e metadados.',
            'process' => 'Busca museológica com parser específico para response.rows e descriptiveNonRepeating.',
            'target' => 'event_enrichments',
        ],
        [
            'key' => 'historical.dpla',
            'name' => 'DPLA / National Archives',
            'entity' => 'Enriquecimento arquivístico',
            'status' => source_status($dplaEnabled, !empty($dpla['api_key'])),
            'checks' => source_checks($dplaEnabled, true, !empty($dpla['api_key']), false, true),
            'data' => 'Itens arquivísticos, descrição, URL, imagem e direitos.',
            'process' => 'Busca arquivística com parser específico para docs e sourceResource.',
            'target' => 'event_enrichments',
        ],
        [
            'key' => 'historical.openhistoricalmap',
            'name' => 'OpenHistoricalMap',
            'entity' => 'Enriquecimento geográfico',
            'status' => source_status($ohmEnabled, !empty($openHistoricalMap['url']), 'endpoint'),
            'checks' => source_checks($ohmEnabled, false, true, true, !empty($openHistoricalMap['url'])),
            'data' => 'Referência geográfica histórica e metadados retornados.',
            'process' => 'Busca por região ou termo do evento.',
            'target' => 'event_enrichments',
        ],
        [
            'key' => 'news.google_news_rss',
            'name' => 'Google News RSS',
            'entity' => 'Notícia',
            'status' => source_status($newsEnabled, !empty($news['feeds']), 'endpoint'),
            'checks' => source_checks($newsEnabled, false, true, true, !empty($news['feeds'])),
            'data' => 'Título, descrição, link e palavras-chave extraídas.',
            'process' => 'Leitura RSS de Brasil, Mundo e Tecnologia.',
            'target' => 'collected_contexts, current_topics',
        ],
        [
            'key' => 'trends.gdelt',
            'name' => 'GDELT Project',
            'entity' => 'Tendência',
            'status' => source_status($gdeltEnabled, true),
            'checks' => source_checks($gdeltEnabled, false, true, false, true),
            'data' => 'Artigos relevantes, domínio, URL e palavras-chave.',
            'process' => 'Consulta por data, query e relevância híbrida.',
            'target' => 'collected_contexts, current_topics',
        ],
        [
            'key' => 'trends.media_cloud',
            'name' => 'Media Cloud',
            'entity' => 'Tendência',
            'status' => source_status($mediaCloudEnabled, !empty($mediaCloud['url']), 'endpoint'),
            'checks' => source_checks($mediaCloudEnabled, !empty($mediaCloud['api_key']), !empty($mediaCloud['api_key']), true, !empty($mediaCloud['url'])),
            'data' => 'Stories/resultados, mídia, URL e palavras-chave.',
            'process' => 'Consulta externa quando URL/API estiver configurada.',
            'target' => 'collected_contexts, current_topics',
        ],
        [
            'key' => 'trends.wikimedia_pageviews',
            'name' => 'Wikimedia Pageviews',
            'entity' => 'Tendência',
            'status' => source_status($pageviewsEnabled, true),
            'checks' => source_checks($pageviewsEnabled, false, true, false, true),
            'data' => 'Páginas mais vistas, views, projeto e URL.',
            'process' => 'Top pageviews do dia anterior para projetos configurados.',
            'target' => 'collected_contexts, current_topics',
        ],
        [
            'key' => 'trends.agencia_brasil',
            'name' => 'Agência Brasil RSS',
            'entity' => 'Tendência/notícia pública',
            'status' => source_status($agenciaEnabled, true),
            'checks' => source_checks($agenciaEnabled, false, true, false, true),
            'data' => 'Título, descrição, link e palavras-chave.',
            'process' => 'Leitura RSS com URL principal e fallback.',
            'target' => 'collected_contexts, current_topics',
        ],
        [
            'key' => 'trends.hacker_news',
            'name' => 'Hacker News API',
            'entity' => 'Tendência tecnológica',
            'status' => source_status($hackerNewsEnabled, true),
            'checks' => source_checks($hackerNewsEnabled, false, true, false, true),
            'data' => 'Top stories, título, score, comentários e URL.',
            'process' => 'Busca lista de top stories e detalha cada item.',
            'target' => 'collected_contexts, current_topics',
        ],
    ];
}
