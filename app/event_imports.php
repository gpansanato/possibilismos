<?php

function normalize_event_identity_text(string $text): string
{
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', (string) $text);
    $words = preg_split('/\s+/u', trim((string) $text));
    $stopwords = [
        'a' => true, 'o' => true, 'e' => true, 'de' => true, 'da' => true, 'do' => true,
        'das' => true, 'dos' => true, 'em' => true, 'no' => true, 'na' => true,
        'nos' => true, 'nas' => true, 'por' => true, 'para' => true, 'com' => true,
        'the' => true, 'of' => true, 'and' => true, 'in' => true, 'on' => true,
        'at' => true, 'to' => true, 'by' => true, 'for' => true,
    ];

    $filtered = [];
    foreach ($words as $word) {
        if ($word === '' || isset($stopwords[$word])) {
            continue;
        }
        $filtered[] = $word;
    }

    return implode(' ', array_slice($filtered, 0, 18));
}

function build_event_key(array $event): string
{
    $canonicalSource = trim((string) ($event['canonical_source'] ?? ''));
    $canonicalId = trim((string) ($event['canonical_id'] ?? ''));
    if ($canonicalSource !== '' && $canonicalId !== '') {
        return mb_strtolower($canonicalSource . ':' . $canonicalId, 'UTF-8');
    }

    $identity = normalize_event_identity_text((string) ($event['title'] ?? ''));
    if ($identity === '') {
        $identity = normalize_event_identity_text((string) ($event['description'] ?? ''));
    }

    $seed = implode('|', [
        (int) ($event['year'] ?? 0),
        (int) ($event['event_month'] ?? 0),
        (int) ($event['event_day'] ?? 0),
        $identity,
    ]);

    return 'historical:' . sha1($seed);
}

function save_event_import(array $item): int
{
    $source = clean_context_text($item['source'] ?? 'unknown');
    $sourceEventId = clean_context_text($item['source_event_id'] ?? '');
    $normalizedKey = clean_context_text($item['normalized_key'] ?? '');
    if ($sourceEventId === '') {
        $sourceEventId = $normalizedKey !== '' ? $normalizedKey : sha1(json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $stmt = db()->prepare(
        'INSERT INTO event_imports
         (run_date, source, source_variant, source_type, source_event_id, source_url, event_month, event_day, event_year,
          raw_title, raw_description, raw_category, raw_location, raw_language, raw_payload_json,
          normalized_key, canonical_event_id, status, error_message, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            source_variant = VALUES(source_variant),
            source_url = VALUES(source_url),
            raw_title = VALUES(raw_title),
            raw_description = VALUES(raw_description),
            raw_category = VALUES(raw_category),
            raw_location = VALUES(raw_location),
            raw_language = VALUES(raw_language),
            raw_payload_json = VALUES(raw_payload_json),
            normalized_key = VALUES(normalized_key),
            canonical_event_id = VALUES(canonical_event_id),
            status = VALUES(status),
            error_message = VALUES(error_message),
            updated_at = NOW()'
    );
    $stmt->execute([
        $item['run_date'],
        $source,
        clean_context_text($item['source_variant'] ?? 'default') ?: 'default',
        clean_context_text($item['source_type'] ?? 'historical_event'),
        mb_substr($sourceEventId, 0, 190, 'UTF-8'),
        $item['source_url'] ?? null,
        (int) $item['event_month'],
        (int) $item['event_day'],
        (int) $item['event_year'],
        mb_substr(clean_context_text($item['raw_title'] ?? ''), 0, 255, 'UTF-8'),
        clean_context_text($item['raw_description'] ?? ''),
        clean_context_text($item['raw_category'] ?? ''),
        clean_context_text($item['raw_location'] ?? ''),
        clean_context_text($item['raw_language'] ?? ''),
        json_encode($item['raw_payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        mb_substr($normalizedKey, 0, 190, 'UTF-8'),
        $item['canonical_event_id'] ?? null,
        $item['status'] ?? 'collected',
        $item['error_message'] ?? null,
    ]);

    $id = (int) db()->lastInsertId();
    if ($id > 0) {
        return $id;
    }

    $stmt = db()->prepare('SELECT id FROM event_imports WHERE source = ? AND source_event_id = ? LIMIT 1');
    $stmt->execute([$source, mb_substr($sourceEventId, 0, 190, 'UTF-8')]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function save_event_source(int $eventId, array $item): void
{
    $source = clean_context_text($item['source'] ?? 'unknown');
    $sourceEventId = clean_context_text($item['source_event_id'] ?? '');
    if ($sourceEventId === '') {
        $sourceEventId = build_event_key($item);
    }

    $stmt = db()->prepare(
        'INSERT INTO event_sources
         (event_id, source, source_variant, source_event_id, source_url, source_title, source_description, source_language, confidence_score, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            source_variant = VALUES(source_variant),
            source_url = VALUES(source_url),
            source_title = VALUES(source_title),
            source_description = VALUES(source_description),
            source_language = VALUES(source_language),
            confidence_score = VALUES(confidence_score),
            updated_at = NOW()'
    );
    $stmt->execute([
        $eventId,
        $source,
        clean_context_text($item['source_variant'] ?? 'default') ?: 'default',
        mb_substr($sourceEventId, 0, 190, 'UTF-8'),
        $item['source_url'] ?? null,
        mb_substr(clean_context_text($item['title'] ?? ''), 0, 255, 'UTF-8'),
        clean_context_text($item['description'] ?? ''),
        clean_context_text($item['language'] ?? ''),
        (float) ($item['confidence_score'] ?? 0),
    ]);
}

function find_existing_canonical_event(array $event): int
{
    $canonicalId = trim((string) ($event['canonical_id'] ?? ''));
    $canonicalSource = trim((string) ($event['canonical_source'] ?? ''));
    if ($canonicalId !== '' && $canonicalSource !== '') {
        $stmt = db()->prepare('SELECT id FROM events WHERE canonical_source = ? AND canonical_id = ? LIMIT 1');
        $stmt->execute([$canonicalSource, $canonicalId]);
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
    }

    $eventKey = build_event_key($event);
    $stmt = db()->prepare('SELECT id FROM events WHERE event_key = ? LIMIT 1');
    $stmt->execute([$eventKey]);
    $id = (int) ($stmt->fetchColumn() ?: 0);
    if ($id > 0) {
        return $id;
    }

    $stmt = db()->prepare(
        'SELECT id FROM events
         WHERE event_month = ? AND event_day = ? AND year = ? AND normalized_title = ?
         LIMIT 1'
    );
    $stmt->execute([
        (int) $event['event_month'],
        (int) $event['event_day'],
        (int) $event['year'],
        $event['normalized_title'],
    ]);

    return (int) ($stmt->fetchColumn() ?: 0);
}

function upsert_canonical_event(array $event): int
{
    $event['normalized_title'] = mb_substr(normalize_event_identity_text((string) ($event['title'] ?? '')), 0, 255, 'UTF-8');
    $event['event_key'] = build_event_key($event);
    $event['wikidata_entities_json'] = event_structured_json($event['wikidata_entities_json'] ?? []);
    $event['wikidata_location_json'] = event_structured_json($event['wikidata_location_json'] ?? []);
    $event['wikidata_relations_json'] = event_structured_json($event['wikidata_relations_json'] ?? []);
    $existingId = find_existing_canonical_event($event);

    if ($existingId > 0) {
        $stmt = db()->prepare(
            'UPDATE events
             SET description = CASE WHEN description = "" THEN ? ELSE description END,
                 category = CASE WHEN category = "" OR category = "historia" THEN ? ELSE category END,
                 region = CASE WHEN region = "" OR region LIKE "Wikipedia %" OR region = "Wikidata" THEN ? ELSE region END,
                 source_url = COALESCE(source_url, ?),
                 canonical_id = COALESCE(canonical_id, ?),
                 canonical_source = COALESCE(canonical_source, ?),
                 canonical_title = COALESCE(canonical_title, ?),
                 wikidata_entities_json = CASE WHEN ? <> "" THEN ? ELSE wikidata_entities_json END,
                 wikidata_location_json = CASE WHEN ? <> "" THEN ? ELSE wikidata_location_json END,
                 wikidata_relations_json = CASE WHEN ? <> "" THEN ? ELSE wikidata_relations_json END,
                 normalized_title = COALESCE(normalized_title, ?),
                 event_key = COALESCE(event_key, ?),
                 confidence_score = GREATEST(confidence_score, ?),
                 last_seen_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([
            $event['description'],
            $event['category'],
            $event['region'],
            $event['source_url'],
            $event['canonical_id'],
            $event['canonical_source'],
            $event['canonical_title'],
            $event['wikidata_entities_json'],
            $event['wikidata_entities_json'],
            $event['wikidata_location_json'],
            $event['wikidata_location_json'],
            $event['wikidata_relations_json'],
            $event['wikidata_relations_json'],
            $event['normalized_title'],
            $event['event_key'],
            (float) ($event['confidence_score'] ?? 0),
            $existingId,
        ]);

        return $existingId;
    }

    $stmt = db()->prepare(
        'INSERT INTO events
         (event_key, event_month, event_day, year, title, normalized_title, description, category, region,
          source_url, canonical_id, canonical_source, canonical_title, wikidata_entities_json, wikidata_location_json,
          wikidata_relations_json, base_score, confidence_score,
          review_status, active, created_at, first_seen_at, last_seen_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", 0, NOW(), NOW(), NOW(), NOW())'
    );
    $stmt->execute([
        $event['event_key'],
        (int) $event['event_month'],
        (int) $event['event_day'],
        (int) $event['year'],
        $event['title'],
        $event['normalized_title'],
        $event['description'],
        $event['category'],
        $event['region'],
        $event['source_url'],
        $event['canonical_id'],
        $event['canonical_source'],
        $event['canonical_title'],
        $event['wikidata_entities_json'],
        $event['wikidata_location_json'],
        $event['wikidata_relations_json'],
        (float) ($event['base_score'] ?? 0),
        (float) ($event['confidence_score'] ?? 0),
    ]);

    return (int) db()->lastInsertId();
}

function persist_normalized_historical_event(array $event, array $source, array $import): int
{
    $eventId = upsert_canonical_event($event);
    if ($eventId <= 0) {
        return 0;
    }

    $source['source_url'] = $source['source_url'] ?? $event['source_url'] ?? null;
    $source['title'] = $source['title'] ?? $event['title'];
    $source['description'] = $source['description'] ?? $event['description'];
    $source['confidence_score'] = $source['confidence_score'] ?? $event['confidence_score'] ?? 0;
    save_event_source($eventId, $source);

    $import['canonical_event_id'] = $eventId;
    $import['status'] = 'linked';
    save_event_import($import);

    return $eventId;
}

function event_structured_json($value): string
{
    if (is_string($value)) {
        return $value;
    }

    if (!is_array($value) || $value === []) {
        return '';
    }

    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
}

function event_import_summary_for_date(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = "linked"), 0) AS linked,
            COALESCE(SUM(status = "ignored"), 0) AS ignored,
            COALESCE(SUM(status = "error"), 0) AS errors
         FROM event_imports
         WHERE run_date = ?'
    );
    $stmt->execute([$runDate]);
    $row = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'linked' => (int) ($row['linked'] ?? 0),
        'ignored' => (int) ($row['ignored'] ?? 0),
        'errors' => (int) ($row['errors'] ?? 0),
    ];
}
