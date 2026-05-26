<?php

function collect_historical_events_for_day(int $month, int $day, ?string $runDate = null): array
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['historical'] ?? [];
    $wikimediaSettings = $config['sources']['wikimedia'] ?? [];
    $maxDuration = max(20, (int) ($settings['max_duration_seconds'] ?? 120));
    $maxEnrichDuringCollection = max(0, (int) ($settings['max_enrich_during_collection'] ?? 0));
    $imported = 0;
    $enriched = 0;
    $found = 0;
    $failures = 0;
    $processedCollectors = 0;
    $haltedByBudget = false;
    $collectorStats = [];
    $started = microtime(true);
    $runDate = $runDate ?: historical_import_run_date($month, $day);
    $collectors = historical_event_collectors($settings, $wikimediaSettings);
    ensure_event_collector_statuses($runDate, $collectors);
    $completedCollectors = completed_event_collectors_for_date($runDate);
    $totalCollectors = count($collectors);
    $alreadyCompleted = 0;
    $pendingCollectorLabels = [];

    foreach ($collectors as $collector) {
        $collectorKey = collector_status_key($collector);
        if (isset($completedCollectors[$collectorKey])) {
            $alreadyCompleted++;
            continue;
        }

        if ((microtime(true) - $started) >= $maxDuration) {
            $haltedByBudget = true;
            $pendingCollectorLabels[] = collector_label($collector);
            continue;
        }

        $processedCollectors++;
        $variantStarted = microtime(true);
        $variantFound = 0;
        $variantImported = 0;
        $variantEnriched = 0;
        $variantFailures = 0;

        try {
            mark_event_collector_status($runDate, $collector, 'running', 0, 0, 0, 'Coletor em execucao.');
            $candidates = collect_historical_event_candidates($collector, $month, $day, $settings);
            $variantFound = count($candidates);
            $found += $variantFound;

            foreach ($candidates as $candidate) {
                $eventId = persist_historical_event_candidate($candidate, $month, $day, $runDate);
                if ($eventId > 0) {
                    $imported++;
                    $variantImported++;
                    if ($maxEnrichDuringCollection > 0 && $enriched < $maxEnrichDuringCollection) {
                        $enrichmentResult = enrich_historical_event($eventId, $candidate['payload'] ?? [], ['light']);
                        $savedEnrichments = $enrichmentResult['saved'];
                        $enriched += $savedEnrichments;
                        $variantEnriched += $savedEnrichments;
                    }
                }
            }
            mark_event_collector_status($runDate, $collector, 'done', $variantFound, $variantImported, $variantFailures, 'Coletor concluido.');
        } catch (Throwable $e) {
            $failures++;
            $variantFailures++;
            mark_event_collector_status($runDate, $collector, 'error', $variantFound, $variantImported, $variantFailures, $e->getMessage());
        }

        $collectorStats[] = [
            'source' => $collector['source'],
            'source_variant' => $collector['source_variant'],
            'label' => $collector['label'] ?? collector_label($collector),
            'group_key' => $collector['group_key'] ?? 'other',
            'group_label' => $collector['group_label'] ?? 'Outros coletores',
            'group_description' => $collector['group_description'] ?? 'Coletores complementares.',
            'found' => $variantFound,
            'imported' => $variantImported,
            'enriched' => $variantEnriched,
            'failures' => $variantFailures,
            'duration' => round(max(0, microtime(true) - $variantStarted), 2),
            'message' => $variantFailures > 0 ? 'Coletor concluido com falhas.' : 'Coletor concluido.',
        ];
    }

    $finalStatus = event_collector_status_summary($runDate);

    return [
        'found' => $found,
        'imported' => $imported,
        'enriched' => $enriched,
        'failures' => $failures,
        'processed_collectors' => $processedCollectors,
        'skipped_collectors' => max(0, $totalCollectors - (int) $finalStatus['done']),
        'halted_by_budget' => $haltedByBudget,
        'max_duration_seconds' => $maxDuration,
        'total_collectors' => $totalCollectors,
        'completed_collectors' => (int) $finalStatus['done'],
        'already_completed_collectors' => $alreadyCompleted,
        'pending_collectors' => (int) $finalStatus['pending'],
        'error_collectors' => (int) $finalStatus['error'],
        'pending_collector_labels' => pending_event_collector_labels($runDate),
        'duration' => round(max(0, microtime(true) - $started), 2),
        'collectors' => $collectorStats,
    ];
}

function collector_status_key(array $collector): string
{
    return $collector['source'] . '|' . $collector['source_variant'];
}

function collector_label(array $collector): string
{
    return $collector['source'] . ' / ' . $collector['source_variant'];
}

function ensure_event_collector_statuses(string $runDate, array $collectors): void
{
    $stmt = db()->prepare(
        'INSERT IGNORE INTO event_collector_statuses
         (run_date, source, source_variant, status, updated_at)
         VALUES (?, ?, ?, "pending", NOW())'
    );

    foreach ($collectors as $collector) {
        $stmt->execute([$runDate, $collector['source'], $collector['source_variant']]);
    }
}

function reset_event_collector_statuses_for_date(string $runDate): void
{
    db()->prepare('DELETE FROM event_collector_statuses WHERE run_date = ?')->execute([$runDate]);
}

function completed_event_collectors_for_date(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT source, source_variant
         FROM event_collector_statuses
         WHERE run_date = ? AND status = "done"'
    );
    $stmt->execute([$runDate]);
    $completed = [];
    foreach ($stmt->fetchAll() as $row) {
        $completed[$row['source'] . '|' . $row['source_variant']] = true;
    }

    return $completed;
}

function mark_event_collector_status(
    string $runDate,
    array $collector,
    string $status,
    int $found,
    int $imported,
    int $errors,
    string $message
): void {
    $stmt = db()->prepare(
        'INSERT INTO event_collector_statuses
         (run_date, source, source_variant, status, found_count, imported_count, error_count, message, started_at, finished_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, CASE WHEN ? = "running" THEN NOW() ELSE NULL END, CASE WHEN ? IN ("done", "error", "skipped") THEN NOW() ELSE NULL END, NOW())
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            found_count = VALUES(found_count),
            imported_count = VALUES(imported_count),
            error_count = VALUES(error_count),
            message = VALUES(message),
            started_at = CASE WHEN VALUES(status) = "running" THEN NOW() ELSE started_at END,
            finished_at = CASE WHEN VALUES(status) IN ("done", "error", "skipped") THEN NOW() ELSE finished_at END,
            updated_at = NOW()'
    );
    $stmt->execute([
        $runDate,
        $collector['source'],
        $collector['source_variant'],
        $status,
        $found,
        $imported,
        $errors,
        mb_substr($message, 0, 1000, 'UTF-8'),
        $status,
        $status,
    ]);
}

function event_collector_status_summary(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT status, COUNT(*) total
         FROM event_collector_statuses
         WHERE run_date = ?
         GROUP BY status'
    );
    $stmt->execute([$runDate]);
    $summary = ['pending' => 0, 'running' => 0, 'done' => 0, 'error' => 0, 'skipped' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $summary[$row['status']] = (int) $row['total'];
    }

    return $summary;
}

function pending_event_collector_labels(string $runDate, int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT source, source_variant
         FROM event_collector_statuses
         WHERE run_date = ? AND status IN ("pending", "error", "running")
         ORDER BY id ASC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([$runDate]);

    return array_map(
        static fn($row) => $row['source'] . ' / ' . $row['source_variant'],
        $stmt->fetchAll()
    );
}

function historical_enrichment_group_labels(): array
{
    return [
        'light' => 'Leve: Wikipedia/Wikimedia',
        'documental' => 'Documental: acervos e arquivos',
        'visual' => 'Visual: imagens e museus',
        'geographic' => 'Geografico: referencias territoriais',
        'all' => 'Completo: todos os grupos ativos',
    ];
}

function normalize_historical_enrichment_group(string $group): string
{
    return array_key_exists($group, historical_enrichment_group_labels()) ? $group : 'light';
}

function historical_enrichment_group_label(string $group): string
{
    $group = normalize_historical_enrichment_group($group);
    return historical_enrichment_group_labels()[$group];
}

function historical_available_enrichment_group_labels(): array
{
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['historical'] ?? [];
    $labels = historical_enrichment_group_labels();
    $available = historical_available_enrichment_groups($settings);

    return array_intersect_key($labels, array_fill_keys($available, true));
}

function enrich_historical_events_for_day(int $month, int $day, string $group = 'light'): array
{
    $group = normalize_historical_enrichment_group($group);
    sync_enriched_at_for_day($month, $day);
    $started = microtime(true);
    $evaluated = 0;
    $enrichedEvents = 0;
    $savedEnrichments = 0;
    $alreadyEnriched = 0;
    $withoutSource = 0;
    $withoutResults = 0;
    $failures = 0;
    $processedEvents = 0;
    $remainingEvents = 0;
    $haltedByBudget = false;

    $stmt = db()->prepare(
        'SELECT e.*,
                COALESCE(en.enrichment_count, 0) AS enrichment_count
         FROM events e
         LEFT JOIN (
            SELECT event_id, COUNT(*) AS enrichment_count
            FROM event_enrichments
            GROUP BY event_id
         ) en ON en.event_id = e.id
         WHERE e.event_month = ? AND e.event_day = ?
         ORDER BY
            CASE WHEN e.enriched_at IS NULL THEN 0 ELSE 1 END,
            COALESCE(en.enrichment_count, 0) ASC,
            e.id ASC'
    );
    $stmt->execute([$month, $day]);
    $events = $stmt->fetchAll();
    $eventIds = array_map(static fn($event) => (int) $event['id'], $events);
    $coverage = enrichment_group_coverage_for_events($eventIds);
    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['historical'] ?? [];
    $maxEventsPerRun = max(1, (int) ($settings['max_enrichment_events_per_run'] ?? 20));
    $maxDuration = max(15, (int) ($settings['max_enrichment_duration_seconds'] ?? 120));
    $availableGroups = historical_available_enrichment_groups($settings);
    $groupsToRun = $group === 'all'
        ? array_values(array_diff($availableGroups, ['all']))
        : [$group];
    $sourceStats = [];

    foreach ($events as $event) {
        $evaluated++;
        $eventId = (int) $event['id'];
        try {
            $pendingGroups = [];
            foreach ($groupsToRun as $groupKey) {
                if (!empty($coverage[$eventId][$groupKey])) {
                    $alreadyEnriched++;
                    continue;
                }
                $pendingGroups[] = $groupKey;
            }

            if (!$pendingGroups) {
                continue;
            }

            if ($processedEvents >= $maxEventsPerRun || (microtime(true) - $started) >= $maxDuration) {
                $haltedByBudget = true;
                $remainingEvents++;
                continue;
            }

            $result = enrich_historical_event((int) $event['id'], [], $pendingGroups);
            $processedEvents++;
            $saved = $result['saved'];
            $withoutSource += $result['without_source'];
            $withoutResults += $result['without_results'];
            $failures += $result['failures'];
            merge_enrichment_source_stats($sourceStats, $result['sources']);
            if ($saved > 0) {
                $enrichedEvents++;
                $savedEnrichments += $saved;
            }
        } catch (Throwable $e) {
            $failures++;
        }
    }

    sync_enriched_at_for_day($month, $day);

    return [
        'evaluated' => $evaluated,
        'enriched_events' => $enrichedEvents,
        'saved_enrichments' => $savedEnrichments,
        'already_enriched' => $alreadyEnriched,
        'processed_events' => $processedEvents,
        'remaining_events' => $remainingEvents,
        'halted_by_budget' => $haltedByBudget,
        'max_events_per_run' => $maxEventsPerRun,
        'max_duration_seconds' => $maxDuration,
        'without_source' => $withoutSource,
        'without_results' => $withoutResults,
        'failures' => $failures,
        'group' => $group,
        'group_label' => historical_enrichment_group_label($group),
        'source_stats' => $sourceStats,
        'duration' => round(max(0, microtime(true) - $started), 2),
    ];
}

function historical_available_enrichment_groups(array $settings): array
{
    $event = ['id' => 0, 'title' => '', 'region' => '', 'source_url' => ''];
    $groups = [];
    foreach (historical_enrichment_sources($event, [], $settings) as $groupKey => $sources) {
        foreach ($sources as $source) {
            if (!empty($source['enabled'])) {
                $groups[] = $groupKey;
                break;
            }
        }
    }

    if (count($groups) > 1) {
        $groups[] = 'all';
    }

    return $groups ?: ['light'];
}

function enrichment_group_coverage_for_events(array $eventIds): array
{
    $eventIds = array_values(array_filter(array_unique(array_map('intval', $eventIds))));
    if (!$eventIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    $stmt = db()->prepare(
        'SELECT event_id, source, role
         FROM event_enrichments
         WHERE event_id IN (' . $placeholders . ')'
    );
    $stmt->execute($eventIds);
    $coverage = [];
    foreach ($stmt->fetchAll() as $row) {
        foreach (enrichment_groups_for_source_role((string) $row['source'], (string) $row['role']) as $group) {
            $coverage[(int) $row['event_id']][$group] = true;
        }
    }

    $stmt = db()->prepare(
        'SELECT event_id, enrichment_group
         FROM event_enrichment_statuses
         WHERE event_id IN (' . $placeholders . ')
           AND status IN ("done", "empty", "skipped")'
    );
    $stmt->execute($eventIds);
    foreach ($stmt->fetchAll() as $row) {
        $coverage[(int) $row['event_id']][(string) $row['enrichment_group']] = true;
    }

    return $coverage;
}

function enrichment_groups_for_source_role(string $source, string $role): array
{
    $map = [
        'light' => [
            ['Wikipedia / Wikimedia', 'context'],
            ['Wikimedia Commons', 'visual'],
        ],
        'documental' => [
            ['Library of Congress', 'document'],
            ['Europeana', 'cultural'],
            ['DPLA / National Archives', 'archive'],
        ],
        'visual' => [
            ['Smithsonian Open Access', 'museum'],
        ],
        'geographic' => [
            ['OpenHistoricalMap', 'geo'],
            ['OpenHistoricalMap', 'geographic'],
        ],
    ];

    $groups = [];
    foreach ($map as $group => $pairs) {
        foreach ($pairs as $pair) {
            if ($source === $pair[0] && $role === $pair[1]) {
                $groups[] = $group;
                break;
            }
        }
    }

    return $groups;
}

function sync_enriched_at_for_day(int $month, int $day): void
{
    db()->prepare(
        'UPDATE events e
         SET e.enriched_at = COALESCE(e.enriched_at, NOW())
         WHERE e.event_month = ? AND e.event_day = ?
           AND EXISTS (SELECT 1 FROM event_enrichments en WHERE en.event_id = e.id)'
    )->execute([$month, $day]);
}

function merge_enrichment_source_stats(array &$target, array $sourceStats): void
{
    foreach ($sourceStats as $source => $stats) {
        if (!isset($target[$source])) {
            $target[$source] = [
                'attempted' => 0,
                'saved' => 0,
                'empty' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        foreach ($target[$source] as $key => $value) {
            $target[$source][$key] += (int) ($stats[$key] ?? 0);
        }
    }
}

function historical_event_collectors(array $settings, array $wikimediaSettings): array
{
    $collectors = [];
    $wikidataVariants = [];
    $wikimediaCollectors = [];

    if (!empty($settings['enabled']) && !empty($settings['wikidata']['enabled'])) {
        foreach (wikidata_priority_collector_variants() as $variant => $definition) {
            $wikidataVariants[$variant] = [
                'source' => 'Wikidata',
                'source_variant' => $variant,
                'definition' => $definition,
            ] + historical_collector_metadata('Wikidata', $variant);
        }
    }

    if (!empty($wikimediaSettings['enabled'])) {
        foreach (($wikimediaSettings['languages'] ?? ['pt', 'en', 'es']) as $language) {
            foreach (($wikimediaSettings['types'] ?? ['selected', 'events']) as $type) {
                $wikimediaCollectors[] = [
                    'source' => 'Wikipedia / Wikimedia',
                    'source_variant' => 'on_this_day_' . $language . '_' . $type,
                    'language' => $language,
                    'type' => $type,
                ] + historical_collector_metadata('Wikipedia / Wikimedia', 'on_this_day_' . $language . '_' . $type, $language, $type);
            }
        }
    }

    if (isset($wikidataVariants['point_in_time'])) {
        $collectors[] = $wikidataVariants['point_in_time'];
        unset($wikidataVariants['point_in_time']);
    }

    foreach ($wikimediaCollectors as $index => $collector) {
        if (($collector['type'] ?? '') === 'selected') {
            $collectors[] = $collector;
            unset($wikimediaCollectors[$index]);
        }
    }

    foreach ($wikidataVariants as $collector) {
        $collectors[] = $collector;
    }

    foreach ($wikimediaCollectors as $collector) {
        $collectors[] = $collector;
    }

    return $collectors;
}

function historical_collector_groups(): array
{
    return [
        'canonical_core' => [
            'label' => 'Nucleo canonico',
            'description' => 'Busca estrutural principal para identificar eventos com ID canonico e data pontual.',
        ],
        'wikimedia_editorial' => [
            'label' => 'Curadoria Wikimedia',
            'description' => 'Efemerides selecionadas em diferentes idiomas, uteis para relevancia editorial inicial.',
        ],
        'wikidata_temporal' => [
            'label' => 'Expansao temporal',
            'description' => 'Eventos marcados por data de inicio ou encerramento, que podem nao aparecer como data pontual.',
        ],
        'wikidata_thematic' => [
            'label' => 'Expansao tematica',
            'description' => 'Consultas Wikidata por recortes editoriais como conflitos, politica, descobertas e publicacoes.',
        ],
        'biographical' => [
            'label' => 'Efemerides biograficas',
            'description' => 'Nascimentos e mortes relevantes tratados separadamente para reduzir mistura com eventos historicos gerais.',
        ],
        'wikimedia_broad' => [
            'label' => 'Wikimedia ampla',
            'description' => 'Eventos gerais do On This Day usados para ampliar cobertura ao final da coleta.',
        ],
    ];
}

function historical_collector_metadata(string $source, string $variant, ?string $language = null, ?string $type = null): array
{
    $groups = historical_collector_groups();
    $groupKey = 'wikidata_thematic';
    $label = $source . ' / ' . $variant;

    if ($source === 'Wikidata') {
        $map = [
            'point_in_time' => ['canonical_core', 'Wikidata: data pontual'],
            'start_time' => ['wikidata_temporal', 'Wikidata: data de inicio'],
            'end_time' => ['wikidata_temporal', 'Wikidata: data de encerramento'],
            'conflicts' => ['wikidata_thematic', 'Wikidata: conflitos'],
            'political_events' => ['wikidata_thematic', 'Wikidata: eventos politicos'],
            'discoveries_inventions' => ['wikidata_thematic', 'Wikidata: descobertas e invencoes'],
            'works_publications' => ['wikidata_thematic', 'Wikidata: obras e publicacoes'],
            'births_deaths' => ['biographical', 'Wikidata: nascimentos'],
            'deaths' => ['biographical', 'Wikidata: mortes'],
        ];
        [$groupKey, $label] = $map[$variant] ?? ['wikidata_thematic', $label];
    } elseif ($source === 'Wikipedia / Wikimedia') {
        $languageLabel = strtoupper((string) $language);
        if ($type === 'selected') {
            $groupKey = 'wikimedia_editorial';
            $label = 'Wikimedia ' . $languageLabel . ': selecionados';
        } else {
            $groupKey = 'wikimedia_broad';
            $label = 'Wikimedia ' . $languageLabel . ': eventos gerais';
        }
    }

    return [
        'label' => $label,
        'group_key' => $groupKey,
        'group_label' => $groups[$groupKey]['label'] ?? 'Outros coletores',
        'group_description' => $groups[$groupKey]['description'] ?? 'Coletores complementares.',
    ];
}

function wikidata_priority_collector_variants(): array
{
    $variants = wikidata_collector_variants();
    $orderedKeys = [
        'point_in_time',
        'start_time',
        'end_time',
        'conflicts',
        'political_events',
        'discoveries_inventions',
        'works_publications',
        'births_deaths',
        'deaths',
    ];

    $ordered = [];
    foreach ($orderedKeys as $key) {
        if (isset($variants[$key])) {
            $ordered[$key] = $variants[$key];
        }
    }

    return $ordered;
}

function collect_historical_event_candidates(array $collector, int $month, int $day, array $settings): array
{
    if ($collector['source'] === 'Wikidata') {
        return array_map(
            static fn($row) => [
                'source' => 'Wikidata',
                'source_variant' => $collector['source_variant'],
                'payload' => $row,
            ],
            fetch_wikidata_events_for_day($month, $day, $settings, $collector['definition'], $collector['source_variant'])
        );
    }

    if ($collector['source'] === 'Wikipedia / Wikimedia') {
        return array_map(
            static fn($event) => [
                'source' => 'Wikipedia / Wikimedia',
                'source_variant' => $collector['source_variant'],
                'language' => $collector['language'],
                'type' => $collector['type'],
                'payload' => $event,
            ],
            fetch_wikimedia_on_this_day($collector['language'], $collector['type'], $month, $day)
        );
    }

    return [];
}

function persist_historical_event_candidate(array $candidate, int $month, int $day, ?string $runDate = null): int
{
    if (($candidate['source'] ?? '') === 'Wikidata') {
        return save_wikidata_historical_event($candidate['payload'] ?? [], $month, $day, $runDate, $candidate['source_variant'] ?? 'point_in_time');
    }

    if (($candidate['source'] ?? '') === 'Wikipedia / Wikimedia') {
        return save_wikimedia_event($candidate['payload'] ?? [], $month, $day, $candidate['language'] ?? 'en', $candidate['type'] ?? 'events', $runDate, $candidate['source_variant'] ?? 'on_this_day');
    }

    return 0;
}

function wikidata_collector_variants(): array
{
    return [
        'point_in_time' => ['date_property' => 'P585'],
        'start_time' => ['date_property' => 'P580'],
        'end_time' => ['date_property' => 'P582'],
        'political_events' => ['date_property' => 'P585', 'keywords' => ['election', 'treaty', 'government', 'revolution', 'political']],
        'conflicts' => ['date_property' => 'P585', 'keywords' => ['battle', 'war', 'conflict', 'siege', 'invasion']],
        'discoveries_inventions' => ['date_property' => 'P585', 'keywords' => ['discovery', 'invention', 'first', 'patent']],
        'works_publications' => ['date_property' => 'P577', 'keywords' => ['book', 'publication', 'film', 'album', 'work']],
        'births_deaths' => ['date_property' => 'P569', 'source_type' => 'birth'],
        'deaths' => ['date_property' => 'P570', 'source_type' => 'death'],
    ];
}

function fetch_wikidata_events_for_day(int $month, int $day, array $settings, ?array $variant = null, string $sourceVariant = 'point_in_time'): array
{
    $variant = $variant ?: ['date_property' => 'P585'];
    $endpoint = $settings['wikidata']['endpoint'] ?? 'https://query.wikidata.org/sparql';
    $dateProperty = preg_replace('/[^A-Z0-9]/', '', (string) ($variant['date_property'] ?? 'P585'));
    $sourceType = $variant['source_type'] ?? 'historical_event';
    $limit = wikidata_variant_limit($settings, $sourceVariant);
    $query = '
SELECT ?item ?itemLabel ?date
       (GROUP_CONCAT(DISTINCT ?typeLabel; separator="|") AS ?typeLabels)
       (GROUP_CONCAT(DISTINCT ?participantLabel; separator="|") AS ?participantLabels)
       (GROUP_CONCAT(DISTINCT ?partOfLabel; separator="|") AS ?partOfLabels)
       (GROUP_CONCAT(DISTINCT ?causeLabel; separator="|") AS ?causeLabels)
       (GROUP_CONCAT(DISTINCT ?effectLabel; separator="|") AS ?effectLabels)
       ?placeLabel ?coord ?countryLabel ?adminLabel ?article WHERE {
  ?item wdt:' . $dateProperty . ' ?date.
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
LIMIT ' . $limit;

    $url = $endpoint . '?' . http_build_query([
        'query' => $query,
        'format' => 'json',
    ]);
    $body = http_get_json($url, historical_user_agent());
    $data = $body ? json_decode($body, true) : null;
    if (!is_array($data) || empty($data['results']['bindings'])) {
        return [];
    }

    $rows = array_map(static function (array $row) use ($sourceVariant, $sourceType, $dateProperty): array {
        $row['_source_variant'] = ['value' => $sourceVariant];
        $row['_source_type'] = ['value' => $sourceType];
        $row['_date_property'] = ['value' => $dateProperty];
        return $row;
    }, $data['results']['bindings']);

    return wikidata_filter_variant_rows($rows, $variant['keywords'] ?? []);
}

function wikidata_variant_limit(array $settings, string $sourceVariant): int
{
    $variantLimits = $settings['wikidata']['variant_limits'] ?? [];
    $limit = (int) ($variantLimits[$sourceVariant] ?? min(12, (int) ($settings['max_import'] ?? 35)));

    return max(1, $limit);
}

function wikidata_filter_variant_rows(array $rows, array $keywords): array
{
    $keywords = array_values(array_filter(array_map(static fn($keyword) => mb_strtolower((string) $keyword, 'UTF-8'), $keywords)));
    if (!$keywords) {
        return $rows;
    }

    return array_values(array_filter($rows, static function (array $row) use ($keywords): bool {
        $haystack = mb_strtolower(implode(' ', [
            $row['itemLabel']['value'] ?? '',
            $row['typeLabels']['value'] ?? '',
            $row['participantLabels']['value'] ?? '',
            $row['partOfLabels']['value'] ?? '',
        ]), 'UTF-8');

        foreach ($keywords as $keyword) {
            if (mb_strpos($haystack, $keyword, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }));
}

function save_wikidata_historical_event(array $row, int $month, int $day, ?string $runDate = null, string $sourceVariant = 'point_in_time'): int
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
        'source_variant' => $sourceVariant,
        'source_event_id' => $sourceVariant . ':' . $wikidataId,
        'source_url' => $itemUrl ?: null,
        'title' => $label,
        'description' => trim(($type ? 'Tipo: ' . $type . '. ' : '') . ($place ? 'Local: ' . $place . '.' : '')),
        'language' => 'mul',
        'confidence_score' => 0.95,
    ];
    $import = [
        'run_date' => $runDate ?: historical_import_run_date($month, $day),
        'source' => 'Wikidata',
        'source_variant' => $sourceVariant,
        'source_type' => clean_context_text($row['_source_type']['value'] ?? 'historical_event'),
        'source_event_id' => $sourceVariant . ':' . $wikidataId,
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

function enrich_historical_event(int $eventId, array $seed = [], ?array $enabledGroups = null): array
{
    $event = event_by_id($eventId);
    if (!$event) {
        return empty_enrichment_result();
    }

    $config = require __DIR__ . '/config.php';
    $settings = $config['sources']['historical'] ?? [];
    $saved = 0;
    $withoutSource = 0;
    $withoutResults = 0;
    $failures = 0;
    $sourceStats = [];

    foreach (historical_enrichment_sources($event, $seed, $settings) as $groupKey => $sources) {
        if ($enabledGroups !== null && !in_array($groupKey, $enabledGroups, true)) {
            continue;
        }

        foreach ($sources as $sourceKey => $source) {
            $sourceLabel = $source['label'];
            if (empty($source['enabled'])) {
                continue;
            }

            if (!isset($sourceStats[$sourceLabel])) {
                $sourceStats[$sourceLabel] = ['attempted' => 0, 'saved' => 0, 'empty' => 0, 'skipped' => 0, 'errors' => 0];
            }

            if (empty($source['available'])) {
                $withoutSource++;
                $sourceStats[$sourceLabel]['skipped']++;
                save_event_enrichment_status($eventId, $groupKey, $sourceLabel, 'skipped', 0, $source['message']);
                continue;
            }

            $sourceStats[$sourceLabel]['attempted']++;
            try {
                $sourceSaved = (int) $source['callback']();
                $saved += $sourceSaved;
                if ($sourceSaved > 0) {
                    $sourceStats[$sourceLabel]['saved'] += $sourceSaved;
                    save_event_enrichment_status($eventId, $groupKey, $sourceLabel, 'done', $sourceSaved, 'Enriquecimento salvo.');
                } else {
                    $withoutResults++;
                    $sourceStats[$sourceLabel]['empty']++;
                    save_event_enrichment_status($eventId, $groupKey, $sourceLabel, 'empty', 0, 'Fonte consultada sem resultado aplicavel.');
                }
            } catch (Throwable $e) {
                $failures++;
                $sourceStats[$sourceLabel]['errors']++;
                save_event_enrichment_status($eventId, $groupKey, $sourceLabel, 'error', 0, $e->getMessage());
            }
        }
    }

    if ($saved > 0) {
        db()->prepare('UPDATE events SET enriched_at = NOW() WHERE id = ?')->execute([$eventId]);
    }

    return [
        'saved' => $saved,
        'without_source' => $withoutSource,
        'without_results' => $withoutResults,
        'failures' => $failures,
        'sources' => $sourceStats,
    ];
}

function empty_enrichment_result(): array
{
    return [
        'saved' => 0,
        'without_source' => 0,
        'without_results' => 0,
        'failures' => 0,
        'sources' => [],
    ];
}

function historical_enrichment_sources(array $event, array $seed, array $settings): array
{
    $article = historical_event_article_url($event, $seed);

    return [
        'light' => [
            'wikipedia' => [
                'label' => 'Wikipedia REST Summary',
                'enabled' => !empty($settings['wikipedia']['enabled']),
                'available' => $article !== '',
                'message' => $article !== '' ? 'Artigo Wikipedia localizado.' : 'Sem artigo Wikipedia associado ao evento.',
                'callback' => static fn() => enrich_event_from_wikipedia($event, $article, $settings),
            ],
        ],
        'documental' => [
            'library_of_congress' => [
                'label' => 'Library of Congress',
                'enabled' => !empty($settings['library_of_congress']['enabled']),
                'available' => true,
                'message' => 'Busca documental habilitada.',
                'callback' => static fn() => enrich_event_from_library_of_congress($event, $settings),
            ],
            'europeana' => [
                'label' => 'Europeana',
                'enabled' => !empty($settings['europeana']['enabled']),
                'available' => !empty($settings['europeana']['api_key']),
                'message' => !empty($settings['europeana']['api_key']) ? 'API key configurada.' : 'API key da Europeana ausente.',
                'callback' => static fn() => enrich_event_from_europeana($event, $settings['europeana']),
            ],
            'dpla' => [
                'label' => 'DPLA / National Archives',
                'enabled' => !empty($settings['dpla']['enabled']),
                'available' => !empty($settings['dpla']['api_key']),
                'message' => !empty($settings['dpla']['api_key']) ? 'API key configurada.' : 'API key da DPLA ausente.',
                'callback' => static fn() => enrich_event_from_dpla($event, $settings['dpla']),
            ],
        ],
        'visual' => [
            'smithsonian' => [
                'label' => 'Smithsonian Open Access',
                'enabled' => !empty($settings['smithsonian']['enabled']),
                'available' => !empty($settings['smithsonian']['api_key']),
                'message' => !empty($settings['smithsonian']['api_key']) ? 'API key configurada.' : 'API key do Smithsonian ausente.',
                'callback' => static fn() => enrich_event_from_smithsonian($event, $settings['smithsonian']),
            ],
        ],
        'geographic' => [
            'openhistoricalmap' => [
                'label' => 'OpenHistoricalMap',
                'enabled' => !empty($settings['openhistoricalmap']['enabled']),
                'available' => !empty($settings['openhistoricalmap']['url']),
                'message' => !empty($settings['openhistoricalmap']['url']) ? 'Endpoint configurado.' : 'Endpoint OpenHistoricalMap ausente.',
                'callback' => static fn() => enrich_event_from_openhistoricalmap($event, $settings['openhistoricalmap']),
            ],
        ],
    ];
}

function save_event_enrichment_status(int $eventId, string $group, string $source, string $status, int $resultCount, string $message): void
{
    $stmt = db()->prepare(
        'INSERT INTO event_enrichment_statuses
         (event_id, enrichment_group, source, status, result_count, message, attempted_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            result_count = VALUES(result_count),
            message = VALUES(message),
            attempted_at = VALUES(attempted_at),
            updated_at = NOW()'
    );
    $stmt->execute([
        $eventId,
        $group,
        $source,
        $status,
        $resultCount,
        mb_substr($message, 0, 1000, 'UTF-8'),
    ]);
}

function historical_event_article_url(array $event, array $seed): string
{
    $article = (string) ($seed['article']['value'] ?? '');
    if ($article !== '') {
        return $article;
    }

    $sourceUrl = (string) ($event['source_url'] ?? '');
    if (strpos($sourceUrl, 'wikipedia.org/wiki/') !== false) {
        return $sourceUrl;
    }

    $stmt = db()->prepare(
        'SELECT metadata_json
         FROM event_enrichments
         WHERE event_id = ? AND source = "Wikidata"
         ORDER BY updated_at DESC, id DESC
         LIMIT 1'
    );
    $stmt->execute([(int) ($event['id'] ?? 0)]);
    $metadata = $stmt->fetchColumn();
    if (!is_string($metadata) || $metadata === '') {
        return '';
    }

    $decoded = json_decode($metadata, true);
    if (!is_array($decoded)) {
        return '';
    }

    return (string) ($decoded['article']['value'] ?? '');
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
