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
        'SELECT r.*, e.year, e.title, e.description, e.category, e.region, e.source_url, e.review_status
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
        'SELECT r.*, e.year, e.title, e.description, e.category, e.region, e.source_url, e.review_status
         FROM daily_rankings r
         JOIN events e ON e.id = r.event_id
         WHERE r.run_date = ?
         ORDER BY r.score DESC, r.id ASC'
    );
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}
