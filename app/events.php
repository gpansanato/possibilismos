<?php

function events_for_day(int $month, int $day): array
{
    $stmt = db()->prepare(
        'SELECT * FROM events WHERE event_month = ? AND event_day = ? AND review_status = "approved" ORDER BY base_score DESC, year ASC'
    );
    $stmt->execute([$month, $day]);

    return $stmt->fetchAll();
}

function events_count_for_day(int $month, int $day): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM events WHERE event_month = ? AND event_day = ? AND review_status = "approved"');
    $stmt->execute([$month, $day]);

    return (int) $stmt->fetchColumn();
}

function event_review_status_label(string $status): string
{
    return [
        'pending' => 'Nao avaliado',
        'approved' => 'Aprovado',
        'rejected' => 'Reprovado',
    ][$status] ?? 'Nao avaliado';
}

function event_review_status_class(string $status): string
{
    return [
        'pending' => 'is-pending',
        'approved' => 'is-approved',
        'rejected' => 'is-rejected',
    ][$status] ?? 'is-pending';
}

function event_active_from_review_status(string $status): int
{
    return $status === 'approved' ? 1 : 0;
}

function published_rankings_for_date(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT r.*, e.year, e.title, e.description, e.category, e.region, e.source_url, e.review_status, e.base_score
         FROM daily_rankings r
         JOIN events e ON e.id = r.event_id
         WHERE r.run_date = ? AND r.status = "approved" AND e.review_status = "approved"
         ORDER BY r.score DESC, r.id ASC'
    );
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}

function rankings_for_date(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT r.*, e.year, e.title, e.description, e.category, e.region, e.source_url, e.review_status, e.base_score
         FROM daily_rankings r
         JOIN events e ON e.id = r.event_id
         WHERE r.run_date = ?
         ORDER BY r.score DESC, r.id ASC'
    );
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}

function historical_events_for_day(int $month, int $day): array
{
    $stmt = db()->prepare(
        'SELECT * FROM events WHERE event_month = ? AND event_day = ? ORDER BY base_score DESC, FIELD(review_status, "approved", "pending", "rejected"), year ASC'
    );
    $stmt->execute([$month, $day]);

    return $stmt->fetchAll();
}

function historical_events_count_for_day(int $month, int $day): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM events WHERE event_month = ? AND event_day = ?');
    $stmt->execute([$month, $day]);

    return (int) $stmt->fetchColumn();
}

function event_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $event = $stmt->fetch();

    return $event ?: null;
}

function event_rankings(int $eventId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM daily_rankings WHERE event_id = ? ORDER BY run_date DESC, score DESC, id DESC'
    );
    $stmt->execute([$eventId]);

    return $stmt->fetchAll();
}

function event_enrichments(int $eventId): array
{
    $stmt = db()->prepare(
        'SELECT * FROM event_enrichments WHERE event_id = ? ORDER BY role, source, updated_at DESC'
    );
    $stmt->execute([$eventId]);

    return $stmt->fetchAll();
}

function event_enrichments_count(int $eventId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM event_enrichments WHERE event_id = ?');
    $stmt->execute([$eventId]);

    return (int) $stmt->fetchColumn();
}

function historical_collection_summary_for_day(int $month, int $day): array
{
    $stmt = db()->prepare(
        'SELECT
            COUNT(*) AS total,
            COALESCE(SUM(e.review_status = "pending"), 0) AS pending,
            COALESCE(SUM(e.review_status = "approved"), 0) AS approved,
            COALESCE(SUM(e.review_status = "rejected"), 0) AS rejected,
            COALESCE(SUM(e.canonical_id IS NOT NULL AND e.canonical_id <> ""), 0) AS canonical,
            COALESCE(SUM(e.image_url IS NOT NULL AND e.image_url <> ""), 0) AS with_image,
            COALESCE(SUM(COALESCE(en.enrichment_count, 0) > 0), 0) AS enriched,
            COALESCE(SUM(COALESCE(en.enrichment_count, 0) = 0), 0) AS not_enriched,
            COALESCE(SUM(COALESCE(en.enrichment_count, 0)), 0) AS enrichment_records
         FROM events e
         LEFT JOIN (
            SELECT event_id, COUNT(*) AS enrichment_count
            FROM event_enrichments
            GROUP BY event_id
         ) en ON en.event_id = e.id
         WHERE e.event_month = ? AND e.event_day = ?'
    );
    $stmt->execute([$month, $day]);
    $row = $stmt->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'pending' => (int) ($row['pending'] ?? 0),
        'approved' => (int) ($row['approved'] ?? 0),
        'rejected' => (int) ($row['rejected'] ?? 0),
        'canonical' => (int) ($row['canonical'] ?? 0),
        'with_image' => (int) ($row['with_image'] ?? 0),
        'enriched' => (int) ($row['enriched'] ?? 0),
        'not_enriched' => (int) ($row['not_enriched'] ?? 0),
        'enrichment_records' => (int) ($row['enrichment_records'] ?? 0),
    ];
}

function current_topics_count_for_date(string $runDate): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM current_topics WHERE run_date = ?');
    $stmt->execute([$runDate]);

    return (int) $stmt->fetchColumn();
}

function current_topics_count_for_date_and_source(string $runDate, string $prefix): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM current_topics WHERE run_date = ? AND source LIKE ?');
    $stmt->execute([$runDate, $prefix . ':%']);

    return (int) $stmt->fetchColumn();
}

function rankings_count_for_date(string $runDate): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM daily_rankings WHERE run_date = ?');
    $stmt->execute([$runDate]);

    return (int) $stmt->fetchColumn();
}

function events_count_by_review_status(?int $month = null, ?int $day = null): array
{
    $where = [];
    $params = [];

    if ($month !== null) {
        $where[] = 'event_month = ?';
        $params[] = $month;
    }

    if ($day !== null) {
        $where[] = 'event_day = ?';
        $params[] = $day;
    }

    $sql = 'SELECT review_status, COUNT(*) total FROM events';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' GROUP BY review_status';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['review_status']] = (int) $row['total'];
    }

    return $counts;
}
