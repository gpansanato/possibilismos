<?php

function events_for_day(int $month, int $day): array
{
    $stmt = db()->prepare(
        'SELECT * FROM events WHERE event_month = ? AND event_day = ? AND active = 1 ORDER BY base_score DESC, year ASC'
    );
    $stmt->execute([$month, $day]);

    return $stmt->fetchAll();
}

function published_rankings_for_date(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT r.*, e.year, e.title, e.description, e.category, e.region, e.source_url
         FROM daily_rankings r
         JOIN events e ON e.id = r.event_id
         WHERE r.run_date = ? AND r.status = "approved"
         ORDER BY r.score DESC, r.id ASC'
    );
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}

function rankings_for_date(string $runDate): array
{
    $stmt = db()->prepare(
        'SELECT r.*, e.year, e.title, e.description, e.category, e.region, e.source_url
         FROM daily_rankings r
         JOIN events e ON e.id = r.event_id
         WHERE r.run_date = ?
         ORDER BY r.score DESC, r.id ASC'
    );
    $stmt->execute([$runDate]);

    return $stmt->fetchAll();
}
