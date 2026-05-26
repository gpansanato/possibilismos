<?php
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$config = require __DIR__ . '/../app/config.php';
$sources = source_catalog($config['sources'] ?? []);

render_page_start('Fontes', 'sources', 'admin', 'Catalogo operacional das fontes externas e requisitos de configuracao.');
?>
    <section class="panel">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Fontes externas</p>
                <h2>Catalogo operacional</h2>
                <p>Esta tela centraliza as fontes disponiveis e mostra se cada uma esta pronta para uso. A execucao dos processamentos fica na tela Coletas.</p>
            </div>
            <a class="button button-secondary" href="/admin/collections.php">Abrir coletas</a>
        </div>
        <div class="table-wrap table-wrap--plain">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fonte</th>
                        <th>Entidade</th>
                        <th>Status</th>
                        <th>Configuracao</th>
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
                            <td data-label="Configuracao">
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
    $ready = $enabled && (!$credentialRequired || $hasCredential) && (!$endpointRequired || $hasEndpoint);
    $checks = [
        ['label' => $enabled ? 'habilitada' : 'desativada', 'ok' => $enabled],
    ];

    $checks[] = $credentialRequired
        ? ['label' => $hasCredential ? 'credencial ok' : 'sem credencial', 'ok' => $hasCredential]
        : ['label' => 'sem credencial', 'ok' => true];

    $checks[] = $endpointRequired
        ? ['label' => $hasEndpoint ? 'endpoint ok' : 'sem endpoint', 'ok' => $hasEndpoint]
        : ['label' => 'endpoint padrao', 'ok' => true];

    $checks[] = ['label' => $ready ? 'pronta' : 'pendente', 'ok' => $ready];

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

    return [
        source_catalog_item('historical.wikidata', 'Wikidata', 'Evento historico', !empty($historical['enabled']) && !empty($historical['wikidata']['enabled']), true, false, true, false, 'ID canonico, data, ano, tipo, local, artigo, entidades, localizacao e relacoes.', 'Matriz SPARQL por variantes: point in time, start/end time, politica, conflitos, descobertas, publicacoes, nascimentos e mortes.', 'events, event_imports, event_sources, event_enrichments'),
        source_catalog_item('wikimedia.onthisday', 'Wikipedia / Wikimedia On This Day', 'Evento historico', !empty($wikimedia['enabled']), true, false, true, false, 'Ano, descricao, pagina relacionada, idioma, tipo de efemeride e variante de fonte.', 'Coleta paralela sempre ativa para idiomas configurados, inicialmente pt, en e es.', 'events, event_imports, event_sources, event_enrichments'),
        source_catalog_item('historical.wikipedia', 'Wikipedia REST Summary', 'Enriquecimento contextual', !empty($historical['wikipedia']['enabled']), true, false, true, false, 'Resumo, URL canonica, thumbnail e metadados da pagina.', 'Enriquecimento a partir do artigo associado ao evento.', 'events.image_url, event_enrichments'),
        source_catalog_item('historical.commons', 'Wikimedia Commons', 'Enriquecimento visual', !empty($historical['commons']['enabled']), true, false, true, false, 'Imagem associada ao resumo Wikimedia e licenca indicada.', 'Registro visual derivado do thumbnail da Wikipedia.', 'event_enrichments'),
        source_catalog_item('historical.library_of_congress', 'Library of Congress', 'Enriquecimento documental', !empty($historical['library_of_congress']['enabled']), true, false, true, false, 'Titulo, descricao/data, URL, imagem e direitos.', 'Busca por termo do evento e uso do primeiro resultado.', 'event_enrichments'),
        source_catalog_item('historical.europeana', 'Europeana', 'Enriquecimento cultural', !empty($europeana['enabled']), !empty($europeana['api_key']), true, !empty($europeana['api_key']), false, 'Obras, objetos, descricao, preview, direitos e identificador.', 'Busca cultural com parser especifico.', 'event_enrichments'),
        source_catalog_item('historical.smithsonian', 'Smithsonian Open Access', 'Enriquecimento museologico', !empty($smithsonian['enabled']), !empty($smithsonian['api_key']), true, !empty($smithsonian['api_key']), false, 'Objetos, titulos, descricoes, imagens e metadados.', 'Busca museologica com parser especifico.', 'event_enrichments'),
        source_catalog_item('historical.dpla', 'DPLA / National Archives', 'Enriquecimento arquivistico', !empty($dpla['enabled']), !empty($dpla['api_key']), true, !empty($dpla['api_key']), false, 'Itens arquivisticos, descricao, URL, imagem e direitos.', 'Busca arquivistica com parser especifico.', 'event_enrichments'),
        source_catalog_item('historical.openhistoricalmap', 'OpenHistoricalMap', 'Enriquecimento geografico', !empty($openHistoricalMap['enabled']), !empty($openHistoricalMap['url']), false, true, true, 'Referencia geografica historica e metadados retornados.', 'Busca por regiao ou termo do evento.', 'event_enrichments', 'endpoint'),
        source_catalog_item('news.google_news_rss', 'Google News RSS', 'Noticia', !empty($news['enabled']), !empty($news['feeds']), false, true, true, 'Titulo, descricao, link e palavras-chave extraidas.', 'Leitura RSS de fontes configuradas.', 'collected_contexts, current_topics', 'endpoint'),
        source_catalog_item('trends.gdelt', 'GDELT Project', 'Tendencia', !empty($trends['enabled']) && !empty($trends['gdelt']['enabled']), true, false, true, false, 'Artigos relevantes, dominio, URL e palavras-chave.', 'Consulta por data, query e relevancia hibrida.', 'collected_contexts, current_topics'),
        source_catalog_item('trends.media_cloud', 'Media Cloud', 'Tendencia', !empty($mediaCloud['enabled']), !empty($mediaCloud['url']), !empty($mediaCloud['api_key']), !empty($mediaCloud['api_key']), true, 'Stories/resultados, midia, URL e palavras-chave.', 'Consulta externa quando URL/API estiver configurada.', 'collected_contexts, current_topics', 'endpoint'),
        source_catalog_item('trends.wikimedia_pageviews', 'Wikimedia Pageviews', 'Tendencia', !empty($trends['enabled']) && !empty($trends['wikimedia_pageviews']['enabled']), true, false, true, false, 'Paginas mais vistas, views, projeto e URL.', 'Top pageviews do dia anterior para projetos configurados.', 'collected_contexts, current_topics'),
        source_catalog_item('trends.agencia_brasil', 'Agencia Brasil RSS', 'Tendencia/noticia publica', !empty($trends['enabled']) && !empty($trends['agencia_brasil']['enabled']), true, false, true, false, 'Titulo, descricao, link e palavras-chave.', 'Leitura RSS com URL principal e fallback.', 'collected_contexts, current_topics'),
        source_catalog_item('trends.hacker_news', 'Hacker News API', 'Tendencia tecnologica', !empty($trends['enabled']) && !empty($trends['hacker_news']['enabled']), true, false, true, false, 'Top stories, titulo, score, comentarios e URL.', 'Busca lista de top stories e detalha cada item.', 'collected_contexts, current_topics'),
    ];
}

function source_catalog_item(
    string $key,
    string $name,
    string $entity,
    bool $enabled,
    bool $configured,
    bool $credentialRequired,
    bool $hasCredential,
    bool $endpointRequired,
    string $data,
    string $process,
    string $target,
    string $missingLabel = 'credencial'
): array {
    return [
        'key' => $key,
        'name' => $name,
        'entity' => $entity,
        'status' => source_status($enabled, $configured, $missingLabel),
        'checks' => source_checks($enabled, $credentialRequired, $hasCredential, $endpointRequired, $configured),
        'data' => $data,
        'process' => $process,
        'target' => $target,
    ];
}
