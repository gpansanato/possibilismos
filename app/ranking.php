<?php

function run_daily_ranking(?string $runDate = null, ?int $limit = null): array
{
    $config = require __DIR__ . '/config.php';
    $today = today_key();
    $runDate = $runDate ?: $today['date'];
    $limit = $limit ?: (int) $config['cron']['default_limit'];

    import_historical_events_for_today();
    collect_daily_news_topics($runDate);

    return apply_daily_priority_score($runDate, $limit);
}

function collect_daily_news_topics(?string $runDate = null): array
{
    $runDate = $runDate ?: today_key()['date'];
    db()->prepare('DELETE FROM current_topics WHERE run_date = ?')->execute([$runDate]);

    return current_topics_for_today($runDate);
}

function apply_daily_priority_score(?string $runDate = null, ?int $limit = null): array
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

        $topics = topics_for_date($runDate);
        if (!$topics) {
            $topics = collect_daily_news_topics($runDate);
        }

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
    $categoryCounts = [];

    foreach ($events as $event) {
        $components = score_event_components($event, $topics, $currentYear);
        $category = (string) $event['category'];
        $diversityPenalty = min(8, ($categoryCounts[$category] ?? 0) * 4);
        $components['diversity'] = -$diversityPenalty;
        $score = array_sum($components);
        $reasons = build_score_reasons($event, $components, $currentYear);
        $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;

        $ranked[] = [
            'event' => $event,
            'score' => $score,
            'reasons' => $reasons,
            'context_summary' => build_context_summary($event, $reasons),
        ];
    }

    usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);

    return $ranked;
}

function score_event_components(array $event, array $topics, int $currentYear): array
{
    $baseScore = max(0, min(100, (float) $event['base_score']));
    $components = [
        'historical' => $baseScore * 0.45,
        'news' => 0.0,
        'anniversary' => 0.0,
        'category' => 0.0,
        'diversity' => 0.0,
    ];

    $anniversary = $currentYear - (int) $event['year'];
    if ($anniversary > 0) {
        $components['anniversary'] = anniversary_score($anniversary);
    }

    $match = event_news_match_score($event, $topics);
    $components['news'] = $match['score'];
    $components['category'] = category_context_score((string) $event['category'], $topics);

    return $components;
}

function anniversary_score(int $anniversary): float
{
    if (in_array($anniversary, [50, 100, 150, 200, 250, 500], true)) {
        return 18.0;
    }

    if (in_array($anniversary, [10, 25, 75], true)) {
        return 10.0;
    }

    if ($anniversary % 100 === 0) {
        return 18.0;
    }

    if ($anniversary % 50 === 0) {
        return 14.0;
    }

    if ($anniversary % 25 === 0) {
        return 8.0;
    }

    return 0.0;
}

function event_news_match_score(array $event, array $topics): array
{
    $eventText = normalize_score_text(
        $event['title'] . ' ' . $event['description'] . ' ' . $event['category'] . ' ' . $event['region']
    );
    $matchedTopics = 0;
    $matchedKeywords = [];

    foreach ($topics as $topic) {
        $keywords = topic_keywords($topic);
        $topicMatches = 0;

        foreach ($keywords as $keyword) {
            if (mb_strlen($keyword, 'UTF-8') < 4) {
                continue;
            }

            if (mb_strpos($eventText, $keyword, 0, 'UTF-8') !== false) {
                $topicMatches++;
                $matchedKeywords[$keyword] = true;
            }
        }

        if ($topicMatches > 0) {
            $matchedTopics++;
        }
    }

    $score = min(32, ($matchedTopics * 6) + (count($matchedKeywords) * 3));

    return [
        'score' => (float) $score,
        'topics' => $matchedTopics,
        'keywords' => array_keys($matchedKeywords),
    ];
}

function category_context_score(string $category, array $topics): float
{
    $category = normalize_score_text($category);
    if ($category === '') {
        return 0.0;
    }

    $matches = 0;
    foreach ($topics as $topic) {
        $text = normalize_score_text($topic['title'] . ' ' . $topic['keywords']);
        if (mb_strpos($text, $category, 0, 'UTF-8') !== false) {
            $matches++;
        }
    }

    return (float) min(12, $matches * 4);
}

function topic_keywords(array $topic): array
{
    $text = normalize_score_text($topic['keywords'] ?? '');
    $keywords = preg_split('/\s+/u', $text);

    return array_values(array_filter(array_unique($keywords)));
}

function normalize_score_text(string $text): string
{
    $text = mb_strtolower(strip_tags($text), 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

    return trim(preg_replace('/\s+/u', ' ', (string) $text));
}

function build_score_reasons(array $event, array $components, int $currentYear): array
{
    $reasons = [];
    $reasons[] = 'relevancia historica ' . number_format((float) $event['base_score'], 1);

    if ($components['news'] > 0) {
        $reasons[] = 'conexao com noticias do dia +' . number_format($components['news'], 1);
    }

    if ($components['anniversary'] > 0) {
        $anniversary = $currentYear - (int) $event['year'];
        $reasons[] = 'aniversario de ' . $anniversary . ' anos';
    }

    if ($components['category'] > 0) {
        $reasons[] = 'categoria em pauta +' . number_format($components['category'], 1);
    }

    if ($components['diversity'] < 0) {
        $reasons[] = 'ajuste de diversidade ' . number_format($components['diversity'], 1);
    }

    return array_values(array_unique($reasons));
}

function build_context_summary(array $event, array $reasons): string
{
    return 'Este fato foi priorizado por ' . implode(', ', $reasons) . '.';
}
