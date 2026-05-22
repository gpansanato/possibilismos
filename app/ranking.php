<?php

function run_daily_ranking(?string $runDate = null, ?int $limit = null): array
{
    $config = require __DIR__ . '/config.php';
    $today = today_key();
    $runDate = $runDate ?: $today['date'];
    $limit = $limit ?: (int) $config['cron']['default_limit'];

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO daily_runs (run_date, status, started_at)
             VALUES (?, "running", NOW())
             ON DUPLICATE KEY UPDATE status = "running", started_at = NOW(), finished_at = NULL'
        );
        $stmt->execute([$runDate]);

        import_historical_events_for_today();

        $pdo->prepare('DELETE FROM current_topics WHERE run_date = ?')->execute([$runDate]);
        $topics = current_topics_for_today($runDate);
        $events = events_for_day($today['month'], $today['day']);
        $ranked = rank_events($events, $topics, $today['year']);

        $insert = $pdo->prepare(
            'INSERT INTO daily_rankings
             (run_date, event_id, score, reasons, context_summary, status, created_at)
             VALUES (?, ?, ?, ?, ?, "suggested", NOW())
             ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                reasons = VALUES(reasons),
                context_summary = VALUES(context_summary)'
        );

        foreach (array_slice($ranked, 0, $limit) as $item) {
            $insert->execute([
                $runDate,
                $item['event']['id'],
                $item['score'],
                implode('; ', $item['reasons']),
                $item['context_summary'],
            ]);
        }

        $pdo->prepare('UPDATE daily_runs SET status = "done", finished_at = NOW() WHERE run_date = ?')
            ->execute([$runDate]);

        $pdo->commit();
        return array_slice($ranked, 0, $limit);
    } catch (Throwable $e) {
        $pdo->rollBack();
        $stmt = $pdo->prepare('UPDATE daily_runs SET status = "failed", error_message = ?, finished_at = NOW() WHERE run_date = ?');
        $stmt->execute([$e->getMessage(), $runDate]);
        throw $e;
    }
}

function rank_events(array $events, array $topics, int $currentYear): array
{
    $ranked = [];

    foreach ($events as $event) {
        $score = (float) $event['base_score'];
        $reasons = ['relevancia historica ' . number_format((float) $event['base_score'], 1)];

        $anniversary = $currentYear - (int) $event['year'];
        if ($anniversary > 0 && in_array($anniversary, [10, 25, 50, 75, 100, 150, 200, 250, 500], true)) {
            $score += 20;
            $reasons[] = 'aniversario de ' . $anniversary . ' anos';
        }

        $eventText = mb_strtolower(
            $event['title'] . ' ' . $event['description'] . ' ' . $event['category'] . ' ' . $event['region'],
            'UTF-8'
        );

        foreach ($topics as $topic) {
            $keywords = preg_split('/\s+/', mb_strtolower($topic['keywords'], 'UTF-8'));
            $matches = 0;

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && mb_strpos($eventText, $keyword) !== false) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $bonus = min(15, $matches * 5);
                $score += $bonus;
                $reasons[] = 'conecta com ' . $topic['title'];
            }
        }

        $ranked[] = [
            'event' => $event,
            'score' => $score,
            'reasons' => array_values(array_unique($reasons)),
            'context_summary' => build_context_summary($event, $reasons),
        ];
    }

    usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

    return $ranked;
}

function build_context_summary(array $event, array $reasons): string
{
    return 'Este fato foi priorizado por ' . implode(', ', $reasons) . '.';
}
