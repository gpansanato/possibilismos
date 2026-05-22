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

function current_topics_count_for_date(string $runDate): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM current_topics WHERE run_date = ?');
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
