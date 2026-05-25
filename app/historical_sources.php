<?php

function collect_historical_events_for_day(int $month, int $day, ?string $runDate = null): array
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['historical'] ?? [];
    $maxImport = (int) ($settings['max_import'] ?? 35);
    $imported = 0;
    $enriched = 0;

    if (!empty($settings['enabled']) && !empty($settings['wikidata']['enabled'])) {
        foreach (fetch_wikidata_events_for_day($month, $day, $settings) as $event) {
            if ($imported >= $maxImport) {
                break;
            }

            $eventId = save_wikidata_historical_event($event, $month, $day, $runDate);
            if ($eventId > 0) {
                $imported++;
                $enriched += enrich_historical_event($eventId, $event);
            }
        }
    }

    if ($imported === 0) {
        $imported = import_historical_events_from_wikimedia($month, $day, $maxImport, $runDate);
    }

    return [
        'imported' => $imported,
        'enriched' => $enriched,
    ];
}

function fetch_wikidata_events_for_day(int $month, int $day, array $settings): array
{
    $endpoint = $settings['wikidata']['endpoint'] ?? 'https://query.wikidata.org/sparql';
    $query = '
SELECT ?item ?itemLabel ?date
       (GROUP_CONCAT(DISTINCT ?typeLabel; separator="|") AS ?typeLabels)
       (GROUP_CONCAT(DISTINCT ?participantLabel; separator="|") AS ?participantLabels)
       (GROUP_CONCAT(DISTINCT ?partOfLabel; separator="|") AS ?partOfLabels)
       (GROUP_CONCAT(DISTINCT ?causeLabel; separator="|") AS ?causeLabels)
       (GROUP_CONCAT(DISTINCT ?effectLabel; separator="|") AS ?effectLabels)
       ?placeLabel ?coord ?countryLabel ?adminLabel ?article WHERE {
  ?item wdt:P585 ?date.
  FILTER(MONTH(?date) = ' . (int) $month . ' && DAY(?date) = ' . (int) $day . ')
  OPTIONAL { ?item wdt:P31 ?type. }
  OPTIONAL { ?item wdt:P276 ?place. }
  OPTIONAL { ?place wdt:P625 ?coord. }
  OPTIONAL { ?place wdt:P17 ?country. }
  OPTIONAL { ?place wdt:P131 ?admin. }
  OPTIONAL { ?item wdt:P710 ?participant. }
  OPTIONAL { ?item wdt:P361 ?partOf. }
  OPTIONAL { ?item wdt:P828 ?cause. }
  OPTIONAL { ?item wdt:P1542 ?effect. }
  OPTIONAL {
    ?article schema:about ?item;
             schema:isPartOf <https://en.wikipedia.org/>.
  }
  SERVICE wikibase:label { bd:serviceParam wikibase:language "pt,en". }
}
GROUP BY ?item ?itemLabel ?date ?placeLabel ?coord ?countryLabel ?adminLabel ?article
LIMIT ' . (int) ($settings['max_import'] ?? 35);

    $url = $endpoint . '?' . http_build_query([
        'query' => $query,
        'format' => 'json',
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data) || empty($data['results']['bindings'])) {
        return [];
    }

    return $data['results']['bindings'];
}

function save_wikidata_historical_event(array $row, int $month, int $day, ?string $runDate = null): int
{
    $itemUrl = $row['item']['value'] ?? '';
    $wikidataId = wikidata_id_from_url($itemUrl);
    $label = clean_context_text($row['itemLabel']['value'] ?? '');
    $year = wikidata_year_from_date($row['date']['value'] ?? '');
    if ($wikidataId === '' || $label === '' || $year === null) {
        return 0;
    }

    $title = make_event_title($label, $year);
    $year = normalize_event_year_from_text($label, $year);
    $type = implode(', ', wikidata_binding_list($row, 'typeLabels'));
    $place = clean_context_text($row['placeLabel']['value'] ?? '');
    $entities = wikidata_event_entities($row);
    $location = wikidata_event_location($row, $place);
    $relations = wikidata_event_relations($row);
    $description = $label;
    if ($type !== '') {
        $description .= ' Tipo: ' . $type . '.';
    }
    if ($place !== '') {
        $description .= ' Local: ' . $place . '.';
    }

    $event = [
        'event_month' => $month,
        'event_day' => $day,
        'year' => $year,
        'title' => $title,
        'description' => $description,
        'category' => infer_event_category($description),
        'region' => $place ?: 'Wikidata',
        'source_url' => $itemUrl ?: null,
        'canonical_id' => $wikidataId,
        'canonical_source' => 'Wikidata',
        'canonical_title' => $label,
        'wikidata_entities_json' => $entities,
        'wikidata_location_json' => $location,
        'wikidata_relations_json' => $relations,
        'base_score' => 0.00,
        'confidence_score' => 0.95,
    ];
    $source = [
        'source' => 'Wikidata',
        'source_event_id' => $wikidataId,
        'source_url' => $itemUrl ?: null,
        'title' => $label,
        'description' => trim(($type ? 'Tipo: ' . $type . '. ' : '') . ($place ? 'Local: ' . $place . '.' : '')),
        'language' => 'mul',
        'confidence_score' => 0.95,
    ];
    $import = [
        'run_date' => $runDate ?: historical_import_run_date($month, $day),
        'source' => 'Wikidata',
        'source_type' => 'historical_event',
        'source_event_id' => $wikidataId,
        'source_url' => $itemUrl ?: null,
        'event_month' => $month,
        'event_day' => $day,
        'event_year' => $year,
        'raw_title' => $label,
        'raw_description' => $description,
        'raw_category' => $type,
        'raw_location' => $place,
        'raw_language' => 'mul',
        'raw_payload' => $row,
        'normalized_key' => build_event_key($event),
        'status' => 'normalized',
    ];

    $eventId = persist_normalized_historical_event($event, $source, $import);
    if ($eventId <= 0) {
        return 0;
    }

    save_event_enrichment($eventId, [
        'source' => 'Wikidata',
        'role' => 'canonical',
        'title' => $label,
        'description' => trim(($type ? 'Tipo: ' . $type . '. ' : '') . ($place ? 'Local: ' . $place . '.' : '')),
        'source_url' => $itemUrl,
        'external_id' => $wikidataId,
        'metadata' => $row,
    ]);

    return $eventId;
}

function wikidata_binding_list(array $row, string $key): array
{
    $value = clean_context_text($row[$key]['value'] ?? '');
    if ($value === '') {
        return [];
    }

    return array_values(array_filter(array_unique(array_map(
        static fn($item) => clean_context_text($item),
        explode('|', $value)
    ))));
}

function wikidata_event_entities(array $row): array
{
    return [
        'participants' => wikidata_binding_list($row, 'participantLabels'),
        'types' => wikidata_binding_list($row, 'typeLabels'),
    ];
}

function wikidata_event_location(array $row, string $place): array
{
    return array_filter([
        'place' => $place,
        'country' => clean_context_text($row['countryLabel']['value'] ?? ''),
        'administrative_area' => clean_context_text($row['adminLabel']['value'] ?? ''),
        'coordinates' => clean_context_text($row['coord']['value'] ?? ''),
    ], static fn($value) => $value !== '' && $value !== []);
}

function wikidata_event_relations(array $row): array
{
    return [
        'part_of' => wikidata_binding_list($row, 'partOfLabels'),
        'causes' => wikidata_binding_list($row, 'causeLabels'),
        'effects' => wikidata_binding_list($row, 'effectLabels'),
    ];
}

function import_historical_events_from_wikimedia(int $month, int $day, int $maxImport, ?string $runDate = null): int
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['wikimedia'] ?? [];

    if (empty($settings['enabled'])) {
        return 0;
    }

    $imported = 0;
    foreach (($settings['languages'] ?? ['pt', 'en']) as $language) {
        foreach (($settings['types'] ?? ['selected', 'events']) as $type) {
            foreach (fetch_wikimedia_on_this_day($language, $type, $month, $day) as $event) {
                if ($imported >= $maxImport) {
                    return $imported;
                }

                if (save_wikimedia_event($event, $month, $day, $language, $type, $runDate)) {
                    $imported++;
                }
            }

            if ($imported > 0 && $type === 'selected') {
                break;
            }
        }

        if ($imported > 0) {
            break;
        }
    }

    return $imported;
}

function enrich_historical_event(int $eventId, array $seed = []): int
{
    $event = event_by_id($eventId);
    if (!$event) {
        return 0;
    }

    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['historical'] ?? [];
    $saved = 0;

    $article = $seed['article']['value'] ?? '';
    if (!empty($settings['wikipedia']['enabled']) && $article !== '') {
        $saved += enrich_event_from_wikipedia($event, $article, $settings);
    }
    if (!empty($settings['library_of_congress']['enabled'])) {
        $saved += enrich_event_from_library_of_congress($event, $settings);
    }
    if (!empty($settings['europeana']['enabled']) && !empty($settings['europeana']['api_key'])) {
        $saved += enrich_event_from_europeana($event, $settings['europeana']);
    }
    if (!empty($settings['smithsonian']['enabled']) && !empty($settings['smithsonian']['api_key'])) {
        $saved += enrich_event_from_smithsonian($event, $settings['smithsonian']);
    }
    if (!empty($settings['dpla']['enabled']) && !empty($settings['dpla']['api_key'])) {
        $saved += enrich_event_from_dpla($event, $settings['dpla']);
    }
    if (!empty($settings['openhistoricalmap']['enabled']) && !empty($settings['openhistoricalmap']['url'])) {
        $saved += enrich_event_from_openhistoricalmap($event, $settings['openhistoricalmap']);
    }

    if ($saved > 0) {
        db()->prepare('UPDATE events SET enriched_at = NOW() WHERE id = ?')->execute([$eventId]);
    }

    return $saved;
}

function enrich_event_from_wikipedia(array $event, string $articleUrl, array $settings): int
{
    $title = basename(parse_url($articleUrl, PHP_URL_PATH) ?: '');
    if ($title === '') {
        return 0;
    }

    $url = sprintf($settings['wikipedia']['summary_url'] ?? 'https://en.wikipedia.org/api/rest_v1/page/summary/%s', rawurlencode($title));
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data) || empty($data['title'])) {
        return 0;
    }

    $imageUrl = $data['thumbnail']['source'] ?? null;
    if ($imageUrl && empty($event['image_url'])) {
        db()->prepare('UPDATE events SET image_url = ? WHERE id = ?')->execute([$imageUrl, $event['id']]);
    }

    save_event_enrichment((int) $event['id'], [
        'source' => 'Wikipedia / Wikimedia',
        'role' => 'context',
        'title' => clean_context_text($data['title']),
        'description' => clean_context_text($data['extract'] ?? ''),
        'source_url' => $data['content_urls']['desktop']['page'] ?? $articleUrl,
        'image_url' => $imageUrl,
        'license_label' => 'Wikimedia project license',
        'external_id' => $data['pageid'] ?? $title,
        'metadata' => $data,
    ]);

    if ($imageUrl) {
        save_event_enrichment((int) $event['id'], [
            'source' => 'Wikimedia Commons',
            'role' => 'visual',
            'title' => clean_context_text($data['title']),
            'description' => 'Imagem associada ao resumo Wikimedia.',
            'source_url' => $data['content_urls']['desktop']['page'] ?? $articleUrl,
            'image_url' => $imageUrl,
            'license_label' => 'Ver pagina Wikimedia para licenca',
            'external_id' => 'commons-' . ($data['pageid'] ?? $title),
            'metadata' => $data['thumbnail'] ?? [],
        ]);
    }

    return $imageUrl ? 2 : 1;
}

function enrich_event_from_library_of_congress(array $event, array $settings): int
{
    $url = ($settings['library_of_congress']['url'] ?? 'https://www.loc.gov/search/') . '?' . http_build_query([
        'fo' => 'json',
        'c' => 1,
        'q' => event_search_query($event),
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    $item = $data['results'][0] ?? null;
    if (!is_array($item) || empty($item['title'])) {
        return 0;
    }

    save_event_enrichment((int) $event['id'], [
        'source' => 'Library of Congress',
        'role' => 'document',
        'title' => clean_context_text($item['title']),
        'description' => clean_context_text($item['description'][0] ?? $item['date'] ?? ''),
        'source_url' => $item['url'] ?? null,
        'image_url' => $item['image_url'][0] ?? null,
        'license_label' => $item['rights'] ?? null,
        'external_id' => $item['id'] ?? $item['url'] ?? null,
        'metadata' => $item,
    ]);

    return 1;
}

function enrich_event_from_simple_search(array $event, string $source, string $role, array $settings): int
{
    $query = event_search_query($event);
    $url = ($settings['url'] ?? '') . '?' . http_build_query([
        'query' => $query,
        'q' => $query,
        'wskey' => $settings['api_key'] ?? '',
        'api_key' => $settings['api_key'] ?? '',
        'rows' => 1,
        'page_size' => 1,
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data)) {
        return 0;
    }

    $item = $data['items'][0] ?? $data['docs'][0] ?? $data['response']['rows'][0] ?? [];
    if (!is_array($item)) {
        return 0;
    }

    $title = clean_context_text((string) ($item['title'][0] ?? $item['title'] ?? $item['label'] ?? ''));
    if ($title === '') {
        return 0;
    }

    save_event_enrichment((int) $event['id'], [
        'source' => $source,
        'role' => $role,
        'title' => $title,
        'description' => clean_context_text((string) ($item['description'][0] ?? $item['description'] ?? '')),
        'source_url' => $item['guid'] ?? $item['isShownAt'][0] ?? $item['url'] ?? null,
        'image_url' => $item['edmPreview'][0] ?? $item['thumbnail'] ?? null,
        'license_label' => $item['rights'][0] ?? $item['rights'] ?? null,
        'external_id' => $item['id'] ?? $item['identifier'] ?? null,
        'metadata' => $item,
    ]);

    return 1;
}

function enrich_event_from_europeana(array $event, array $settings): int
{
    $query = event_search_query($event);
    $url = ($settings['url'] ?? 'https://api.europeana.eu/record/v2/search.json') . '?' . http_build_query([
        'query' => $query,
        'wskey' => $settings['api_key'] ?? '',
        'rows' => 1,
        'profile' => 'rich',
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    $item = is_array($data) ? ($data['items'][0] ?? null) : null;
    if (!is_array($item)) {
        return 0;
    }

    $title = first_text_value($item['title'] ?? null);
    if ($title === '') {
        return 0;
    }

    save_event_enrichment((int) $event['id'], [
        'source' => 'Europeana',
        'role' => 'cultural',
        'title' => $title,
        'description' => first_text_value($item['dcDescription'] ?? $item['description'] ?? null),
        'source_url' => first_text_value($item['guid'] ?? $item['link'] ?? $item['edmIsShownAt'] ?? null),
        'image_url' => first_text_value($item['edmPreview'] ?? null),
        'license_label' => first_text_value($item['rights'] ?? null),
        'external_id' => first_text_value($item['id'] ?? null) ?: normalize_context_key($title),
        'metadata' => $item,
    ]);

    return 1;
}

function enrich_event_from_smithsonian(array $event, array $settings): int
{
    $query = event_search_query($event);
    $url = ($settings['url'] ?? 'https://api.si.edu/openaccess/api/v1.0/search') . '?' . http_build_query([
        'api_key' => $settings['api_key'] ?? '',
        'q' => $query,
        'rows' => 1,
        'start' => 0,
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    $row = is_array($data) ? ($data['response']['rows'][0] ?? null) : null;
    if (!is_array($row)) {
        return 0;
    }

    $content = is_array($row['content'] ?? null) ? $row['content'] : [];
    $descriptive = is_array($content['descriptiveNonRepeating'] ?? null) ? $content['descriptiveNonRepeating'] : [];
    $indexed = is_array($content['indexedStructured'] ?? null) ? $content['indexedStructured'] : [];
    $title = first_text_value($descriptive['title'] ?? $row['title'] ?? null);
    if ($title === '') {
        return 0;
    }

    $onlineMedia = $descriptive['online_media']['media'][0] ?? [];
    $imageUrl = is_array($onlineMedia) ? first_text_value($onlineMedia['content'] ?? $onlineMedia['thumbnail'] ?? null) : '';
    $description = first_text_value($descriptive['notes'][0]['content'] ?? $descriptive['record_link'] ?? null);

    save_event_enrichment((int) $event['id'], [
        'source' => 'Smithsonian Open Access',
        'role' => 'museum',
        'title' => $title,
        'description' => $description,
        'source_url' => first_text_value($descriptive['record_link'] ?? null),
        'image_url' => $imageUrl ?: null,
        'license_label' => first_text_value($descriptive['usage_flag'] ?? null) ?: first_text_value($indexed['usage_flag'] ?? null),
        'external_id' => first_text_value($row['id'] ?? null) ?: normalize_context_key($title),
        'metadata' => $row,
    ]);

    return 1;
}

function enrich_event_from_dpla(array $event, array $settings): int
{
    $query = event_search_query($event);
    $url = ($settings['url'] ?? 'https://api.dp.la/v2/items') . '?' . http_build_query([
        'api_key' => $settings['api_key'] ?? '',
        'q' => $query,
        'page_size' => 1,
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    $item = is_array($data) ? ($data['docs'][0] ?? null) : null;
    if (!is_array($item)) {
        return 0;
    }

    $resource = is_array($item['sourceResource'] ?? null) ? $item['sourceResource'] : [];
    $title = first_text_value($resource['title'] ?? $item['title'] ?? null);
    if ($title === '') {
        return 0;
    }

    save_event_enrichment((int) $event['id'], [
        'source' => 'DPLA / National Archives',
        'role' => 'archive',
        'title' => $title,
        'description' => first_text_value($resource['description'] ?? null),
        'source_url' => first_text_value($item['isShownAt'] ?? $item['@id'] ?? null),
        'image_url' => first_text_value($item['object'] ?? null),
        'license_label' => first_text_value($resource['rights'] ?? null),
        'external_id' => first_text_value($item['id'] ?? $item['@id'] ?? null) ?: normalize_context_key($title),
        'metadata' => $item,
    ]);

    return 1;
}

function enrich_event_from_openhistoricalmap(array $event, array $settings): int
{
    $url = $settings['url'] . '?' . http_build_query(['q' => $event['region'] ?: event_search_query($event)]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data)) {
        return 0;
    }

    save_event_enrichment((int) $event['id'], [
        'source' => 'OpenHistoricalMap',
        'role' => 'geo',
        'title' => clean_context_text($event['region'] ?: $event['title']),
        'description' => 'Referencia geografica historica associada ao evento.',
        'source_url' => $url,
        'external_id' => normalize_context_key($event['region'] ?: $event['title']),
        'metadata' => $data,
    ]);

    return 1;
}

function save_event_enrichment(int $eventId, array $item): void
{
    $title = clean_context_text($item['title'] ?? '');
    if ($title === '') {
        return;
    }

    $metadata = $item['metadata'] ?? [];
    $stmt = db()->prepare(
        'INSERT INTO event_enrichments
         (event_id, source, role, title, description, source_url, image_url, license_label, external_id, metadata_json, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            description = VALUES(description),
            source_url = VALUES(source_url),
            image_url = VALUES(image_url),
            license_label = VALUES(license_label),
            metadata_json = VALUES(metadata_json),
            updated_at = NOW()'
    );
    $stmt->execute([
        $eventId,
        clean_context_text($item['source'] ?? 'Fonte historica'),
        clean_context_text($item['role'] ?? 'context'),
        mb_substr($title, 0, 255, 'UTF-8'),
        clean_context_text($item['description'] ?? ''),
        $item['source_url'] ?? null,
        $item['image_url'] ?? null,
        $item['license_label'] ?? null,
        $item['external_id'] ?? normalize_context_key($title),
        json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function event_id_by_canonical_id(string $canonicalId): int
{
    $stmt = db()->prepare('SELECT id FROM events WHERE canonical_source = "Wikidata" AND canonical_id = ? LIMIT 1');
    $stmt->execute([$canonicalId]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function historical_import_run_date(int $month, int $day): string
{
    $today = today_key();
    return sprintf('%04d-%02d-%02d', $today['year'], $month, $day);
}

function wikidata_id_from_url(string $url): string
{
    if (preg_match('/(Q\d+)$/', $url, $matches)) {
        return $matches[1];
    }

    return '';
}

function wikidata_year_from_date(string $date): ?int
{
    if (preg_match('/^(-?)(\d+)/', $date, $matches)) {
        $year = (int) $matches[2];
        return $matches[1] === '-' ? -1 * $year : $year;
    }

    return null;
}

function event_search_query(array $event): string
{
    return trim((string) (($event['canonical_title'] ?? '') ?: ($event['title'] ?? '')));
}

function first_text_value($value): string
{
    if (is_array($value)) {
        foreach ($value as $item) {
            $text = first_text_value($item);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    if ($value === null) {
        return '';
    }

    return clean_context_text((string) $value);
}

function historical_user_agent(): string
{
    $config = require __DIR__ . '/config.php';
    return $config['sources']['wikimedia']['user_agent'] ?? 'PossibilismosMVP/0.1';
}
